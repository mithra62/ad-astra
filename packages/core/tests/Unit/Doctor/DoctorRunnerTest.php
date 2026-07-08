<?php

namespace Tests\Unit\Doctor;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\Doctor\DoctorCheck;
use AdAstra\Doctor\DoctorResult;
use AdAstra\Doctor\DoctorRunner;
use AdAstra\Doctor\DoctorStatus;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class DoctorRunnerTest extends TestCase
{
    /**
     * @param list<string> $deps
     */
    private function makeCheck(string $id, array $deps = [], ?callable $run = null): DoctorCheck
    {
        return new class ($id, $deps, $run) extends AbstractDoctorCheck {
            public function __construct(
                private readonly string $checkId,
                private readonly array $deps,
                private $runner,
            ) {
                $this->id = $checkId;
                $this->name = 'Check ' . $checkId;
            }

            public function dependsOn(): array
            {
                return $this->deps;
            }

            public function run(): iterable
            {
                if ($this->runner !== null) {
                    yield from ($this->runner)();
                }
            }
        };
    }

    private function statuses(DoctorRunner $runner, array $only = [], array $except = []): array
    {
        $statuses = [];
        foreach ($runner->run($only, $except)->entries() as $entry) {
            $statuses[$entry['id']] = array_map(fn ($r) => $r->status, $entry['results']);
        }

        return $statuses;
    }

    public function test_check_yielding_nothing_is_a_pass(): void
    {
        $runner = new DoctorRunner([$this->makeCheck('a.one')]);

        $this->assertSame([DoctorStatus::Pass], $this->statuses($runner)['a.one']);
    }

    public function test_failed_dependency_cascades_to_skip(): void
    {
        $runner = new DoctorRunner([
            $this->makeCheck('a.root', run: fn () => [DoctorResult::fail('broken')]),
            $this->makeCheck('b.child', deps: ['a.root']),
            $this->makeCheck('c.grandchild', deps: ['b.child']),
        ]);

        $statuses = $this->statuses($runner);

        $this->assertSame([DoctorStatus::Fail], $statuses['a.root']);
        $this->assertSame([DoctorStatus::Skip], $statuses['b.child']);
        $this->assertSame([DoctorStatus::Skip], $statuses['c.grandchild']);
    }

    public function test_throwing_check_becomes_fail_and_runner_survives(): void
    {
        $runner = new DoctorRunner([
            $this->makeCheck('a.boom', run: function () {
                throw new RuntimeException('kaboom');
                yield; // makes the closure a generator
            }),
            $this->makeCheck('b.after'),
        ]);

        $report = $runner->run();
        $statuses = $this->statuses($runner);

        $this->assertSame([DoctorStatus::Fail], $statuses['a.boom']);
        $this->assertSame([DoctorStatus::Pass], $statuses['b.after']);

        $failure = $report->entries()[0]['results'][0];
        $this->assertStringContainsString('RuntimeException', $failure->details);
    }

    public function test_only_pulls_in_dependencies_transitively(): void
    {
        $runner = new DoctorRunner([
            $this->makeCheck('a.root'),
            $this->makeCheck('b.mid', deps: ['a.root']),
            $this->makeCheck('c.leaf', deps: ['b.mid']),
            $this->makeCheck('d.unrelated'),
        ]);

        $statuses = $this->statuses($runner, only: ['c.leaf']);

        $this->assertSame(['a.root', 'b.mid', 'c.leaf'], array_keys($statuses));
    }

    public function test_only_matches_categories(): void
    {
        $runner = new DoctorRunner([
            $this->makeCheck('alpha.one'),
            $this->makeCheck('alpha.two'),
            $this->makeCheck('beta.one'),
        ]);

        $statuses = $this->statuses($runner, only: ['alpha']);

        $this->assertSame(['alpha.one', 'alpha.two'], array_keys($statuses));
    }

    public function test_except_drops_checks_but_keeps_needed_dependencies(): void
    {
        $runner = new DoctorRunner([
            $this->makeCheck('a.root'),
            $this->makeCheck('b.child', deps: ['a.root']),
        ]);

        $statuses = $this->statuses($runner, except: ['a.root']);

        // a.root survives because b.child depends on it.
        $this->assertSame(['a.root', 'b.child'], array_keys($statuses));
    }

    public function test_dependencies_execute_before_dependents_regardless_of_registration_order(): void
    {
        $runner = new DoctorRunner([
            $this->makeCheck('b.child', deps: ['a.root']),
            $this->makeCheck('a.root'),
        ]);

        $this->assertSame(['a.root', 'b.child'], array_keys($this->statuses($runner)));
    }

    public function test_category_dependency_expands_to_all_checks_in_it(): void
    {
        $runner = new DoctorRunner([
            $this->makeCheck('db.one', run: fn () => [DoctorResult::fail('down')]),
            $this->makeCheck('db.two'),
            $this->makeCheck('app.check', deps: ['db']),
        ]);

        $statuses = $this->statuses($runner);

        $this->assertSame([DoctorStatus::Skip], $statuses['app.check']);
    }

    public function test_dependency_cycle_throws(): void
    {
        $runner = new DoctorRunner([
            $this->makeCheck('a.one', deps: ['b.two']),
            $this->makeCheck('b.two', deps: ['a.one']),
        ]);

        $this->expectException(LogicException::class);
        $runner->run();
    }

    public function test_disabled_checks_are_excluded(): void
    {
        config(['doctor.disabled' => ['a.off']]);

        $runner = new DoctorRunner([
            $this->makeCheck('a.off'),
            $this->makeCheck('a.on'),
        ]);

        $this->assertSame(['a.on'], array_keys($this->statuses($runner)));
    }
}
