<?php

namespace AdAstra\Doctor;

use LogicException;
use Throwable;

/**
 * Resolves, orders, and executes doctor checks.
 *
 * Three rules govern execution:
 *  - a crashing check becomes a FAIL result; the runner never dies (doctor
 *    is the tool people run when things are already broken),
 *  - a failed dependency cascades to SKIP, not FAIL, so one dead subsystem
 *    produces one failure plus skips instead of a wall of redundant errors,
 *    and
 *  - a disabled check never runs, not even as a dependency: checks that
 *    depend on it *directly* are SKIPped (a check must never execute
 *    against unverified prerequisites), while *category-wide* dependencies
 *    simply ignore disabled members. Naming a disabled check exactly in
 *    --only opts it back in for that run.
 */
final class DoctorRunner
{
    /**
     * @var array<string, DoctorCheck> keyed by check id, registration order
     */
    private array $checks = [];

    /**
     * @param iterable<DoctorCheck> $checks typically the container's
     *        'adastra.doctor.checks' tag
     */
    public function __construct(iterable $checks)
    {
        foreach ($checks as $check) {
            $this->checks[$check->id()] = $check;
        }
    }

    /**
     * @param list<string> $only  check or category ids to run (dependencies
     *                            are pulled in transitively)
     * @param list<string> $except check or category ids to drop (unless a
     *                             surviving check depends on them)
     */
    public function run(array $only = [], array $except = []): DoctorReport
    {
        // Effective disabled set: config ids minus any opted back in by an
        // exact --only id (disabled is for slow/opt-in checks — naming one
        // explicitly is opting in; a category match is not).
        $disabled = array_values(array_diff((array) config('doctor.disabled', []), $only));

        $selected = $this->select($only, $except, $disabled);
        $ordered = $this->sort($selected, $disabled);

        $report = new DoctorReport();
        $outcomes = [];

        foreach ($ordered as $check) {
            $blockedBy = $this->firstBlockingDependency($check, $outcomes, $disabled);

            if ($blockedBy !== null) {
                // No outcome means the dependency never ran (disabled), as
                // opposed to ran-and-failed — say which, it changes the fix.
                $results = [DoctorResult::skip(
                    isset($outcomes[$blockedBy])
                        ? "Skipped: dependency [{$blockedBy}] did not pass"
                        : "Skipped: dependency [{$blockedBy}] is disabled and did not run"
                )];
            } else {
                try {
                    // Drain inside the try — generators execute lazily.
                    $results = [];
                    foreach ($check->run() as $result) {
                        $results[] = $result;
                    }
                    if ($results === []) {
                        $results = [DoctorResult::pass($check->name())];
                    }
                } catch (Throwable $e) {
                    $results = [DoctorResult::fail(
                        "{$check->name()} crashed while running",
                        get_class($e) . ': ' . $e->getMessage(),
                    )];
                }
            }

            $report->add($check, $results);
            $outcomes[$check->id()] = $this->worst($results);
        }

        return $report;
    }

    /**
     * Selector ids that match no registered check id or category — callers
     * should refuse to run with these rather than silently report an empty
     * (healthy-looking) result.
     *
     * @param list<string> $ids
     * @return list<string>
     */
    public function unknownSelectors(array $ids): array
    {
        $categories = array_map(fn (DoctorCheck $check) => $check->category(), $this->checks);

        return array_values(array_filter(
            $ids,
            fn (string $id) => !isset($this->checks[$id]) && !in_array($id, $categories, true),
        ));
    }

    /**
     * Apply --only/--except semantics. Both match check ids and category
     * ids; non-disabled dependencies of surviving checks always run.
     *
     * @param list<string> $only
     * @param list<string> $except
     * @param list<string> $disabled
     * @return array<string, DoctorCheck>
     */
    private function select(array $only, array $except, array $disabled): array
    {
        $pool = array_filter(
            $this->checks,
            fn (DoctorCheck $check) => !in_array($check->id(), $disabled, true)
        );

        $matches = fn (DoctorCheck $check, array $ids) => in_array($check->id(), $ids, true)
            || in_array($check->category(), $ids, true);

        $selected = $only === []
            ? $pool
            : array_filter($pool, fn (DoctorCheck $check) => $matches($check, $only));

        if ($except !== []) {
            $selected = array_filter($selected, fn (DoctorCheck $check) => !$matches($check, $except));
        }

        // Pull dependencies (transitively) back in from the full check set —
        // a check must never execute against unverified prerequisites.
        // Disabled deps stay out: their dependents SKIP instead (see
        // firstBlockingDependency).
        $queue = array_keys($selected);
        while ($queue !== []) {
            $id = array_shift($queue);
            foreach ($this->expandDependencies($this->checks[$id], $disabled) as $depId) {
                if (!isset($selected[$depId])
                    && isset($this->checks[$depId])
                    && !in_array($depId, $disabled, true)) {
                    $selected[$depId] = $this->checks[$depId];
                    $queue[] = $depId;
                }
            }
        }

        return $selected;
    }

    /**
     * Expand a check's dependsOn() entries to concrete check ids. An entry
     * may be a check id or a category id (meaning every check in it).
     *
     * A disabled check is dropped from *category* expansion (the category
     * requirement is "everything that runs in it passes") but kept as a
     * *direct* id — depending on a specific check by name means its outcome
     * is required, so a disabled one blocks the dependent.
     *
     * @param list<string> $disabled
     * @return list<string>
     */
    private function expandDependencies(DoctorCheck $check, array $disabled): array
    {
        $ids = [];
        foreach ($check->dependsOn() as $dep) {
            if (isset($this->checks[$dep])) {
                $ids[] = $dep;
                continue;
            }
            foreach ($this->checks as $id => $candidate) {
                if ($candidate->category() === $dep
                    && $id !== $check->id()
                    && !in_array($id, $disabled, true)) {
                    $ids[] = $id;
                }
            }
            // Unknown ids are ignored here; the guard test rejects them at CI time.
        }

        return $ids;
    }

    /**
     * Topological sort (DFS) over the selected set. Registration order is
     * preserved among independent checks.
     *
     * @param array<string, DoctorCheck> $selected
     * @param list<string> $disabled
     * @return list<DoctorCheck>
     */
    private function sort(array $selected, array $disabled): array
    {
        $ordered = [];
        $state = []; // id => 'visiting' | 'done'

        $visit = function (string $id) use (&$visit, &$ordered, &$state, $selected, $disabled): void {
            if (($state[$id] ?? null) === 'done') {
                return;
            }
            if (($state[$id] ?? null) === 'visiting') {
                throw new LogicException("Doctor check dependency cycle detected at [{$id}].");
            }

            $state[$id] = 'visiting';
            foreach ($this->expandDependencies($selected[$id], $disabled) as $depId) {
                if (isset($selected[$depId])) {
                    $visit($depId);
                }
            }
            $state[$id] = 'done';
            $ordered[] = $selected[$id];
        };

        foreach (array_keys($selected) as $id) {
            $visit($id);
        }

        return $ordered;
    }

    /**
     * @param array<string, DoctorStatus> $outcomes
     * @param list<string> $disabled
     */
    private function firstBlockingDependency(DoctorCheck $check, array $outcomes, array $disabled): ?string
    {
        foreach ($this->expandDependencies($check, $disabled) as $depId) {
            $outcome = $outcomes[$depId] ?? null;

            // null = the dependency never ran (disabled direct dependency) —
            // an unverified prerequisite blocks exactly like a failed one.
            if ($outcome === null || $outcome === DoctorStatus::Fail || $outcome === DoctorStatus::Skip) {
                return $depId;
            }
        }

        return null;
    }

    /**
     * @param list<DoctorResult> $results
     */
    private function worst(array $results): DoctorStatus
    {
        $worst = DoctorStatus::Pass;
        foreach ($results as $result) {
            if ($result->status->weight() > $worst->weight()) {
                $worst = $result->status;
            }
        }

        return $worst;
    }
}
