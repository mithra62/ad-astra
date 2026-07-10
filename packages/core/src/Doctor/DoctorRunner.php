<?php

namespace AdAstra\Doctor;

use LogicException;
use Throwable;

/**
 * Resolves, orders, and executes doctor checks.
 *
 * Two rules govern execution:
 *  - a crashing check becomes a FAIL result; the runner never dies (doctor
 *    is the tool people run when things are already broken), and
 *  - a failed dependency cascades to SKIP, not FAIL, so one dead subsystem
 *    produces one failure plus skips instead of a wall of redundant errors.
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
        $selected = $this->select($only, $except);
        $ordered = $this->sort($selected);

        $report = new DoctorReport();
        $outcomes = [];

        foreach ($ordered as $check) {
            $blockedBy = $this->firstBlockingDependency($check, $outcomes);

            if ($blockedBy !== null) {
                $results = [DoctorResult::skip("Skipped: dependency [{$blockedBy}] did not pass")];
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
     * ids; dependencies of surviving checks always run.
     *
     * @param list<string> $only
     * @param list<string> $except
     * @return array<string, DoctorCheck>
     */
    private function select(array $only, array $except): array
    {
        // Naming a disabled check exactly in --only opts it back in for this
        // run (disabled is for slow/opt-in checks); a category match does not.
        $disabled = (array) config('doctor.disabled', []);
        $pool = array_filter(
            $this->checks,
            fn (DoctorCheck $check) => !in_array($check->id(), $disabled, true)
                || in_array($check->id(), $only, true)
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
        $queue = array_keys($selected);
        while ($queue !== []) {
            $id = array_shift($queue);
            foreach ($this->expandDependencies($this->checks[$id]) as $depId) {
                if (!isset($selected[$depId]) && isset($this->checks[$depId])) {
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
     * @return list<string>
     */
    private function expandDependencies(DoctorCheck $check): array
    {
        $ids = [];
        foreach ($check->dependsOn() as $dep) {
            if (isset($this->checks[$dep])) {
                $ids[] = $dep;
                continue;
            }
            foreach ($this->checks as $id => $candidate) {
                if ($candidate->category() === $dep && $id !== $check->id()) {
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
     * @return list<DoctorCheck>
     */
    private function sort(array $selected): array
    {
        $ordered = [];
        $state = []; // id => 'visiting' | 'done'

        $visit = function (string $id) use (&$visit, &$ordered, &$state, $selected): void {
            if (($state[$id] ?? null) === 'done') {
                return;
            }
            if (($state[$id] ?? null) === 'visiting') {
                throw new LogicException("Doctor check dependency cycle detected at [{$id}].");
            }

            $state[$id] = 'visiting';
            foreach ($this->expandDependencies($selected[$id]) as $depId) {
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
     */
    private function firstBlockingDependency(DoctorCheck $check, array $outcomes): ?string
    {
        foreach ($this->expandDependencies($check) as $depId) {
            $outcome = $outcomes[$depId] ?? null;
            if ($outcome === DoctorStatus::Fail || $outcome === DoctorStatus::Skip) {
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
