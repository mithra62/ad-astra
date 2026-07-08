<?php

namespace Tests\Unit\Doctor;

use AdAstra\Doctor\DoctorCheck;
use Tests\TestCase;

/**
 * Wiring guard: validates every registered doctor check at CI time so
 * mistakes surface here instead of on user machines.
 */
class DoctorChecksGuardTest extends TestCase
{
    /**
     * @return list<DoctorCheck>
     */
    private function registeredChecks(): array
    {
        return iterator_to_array($this->app->tagged('adastra.doctor.checks'), false);
    }

    public function test_check_ids_are_unique_and_well_formed(): void
    {
        $ids = [];

        foreach ($this->registeredChecks() as $check) {
            $id = $check->id();

            $this->assertMatchesRegularExpression(
                '/^[a-z0-9-]+\.[a-z0-9-]+$/',
                $id,
                'Check ids must be "category.check-name" in kebab-case.'
            );
            $this->assertNotContains($id, $ids, "Duplicate check id [{$id}].");
            $this->assertSame(explode('.', $id)[0], $check->category());

            $ids[] = $id;
        }

        $this->assertNotEmpty($ids);
    }

    public function test_every_dependency_references_a_registered_check_or_category(): void
    {
        $checks = $this->registeredChecks();
        $ids = array_map(fn (DoctorCheck $check) => $check->id(), $checks);
        $categories = array_unique(array_map(fn (DoctorCheck $check) => $check->category(), $checks));
        $known = array_merge($ids, $categories);

        foreach ($checks as $check) {
            foreach ($check->dependsOn() as $dep) {
                $this->assertContains(
                    $dep,
                    $known,
                    "Check [{$check->id()}] depends on unknown id/category [{$dep}]."
                );
            }
        }
    }

    public function test_dependency_graph_has_no_cycles(): void
    {
        $checks = [];
        foreach ($this->registeredChecks() as $check) {
            $checks[$check->id()] = $check;
        }

        $expand = function (DoctorCheck $check) use ($checks): array {
            $deps = [];
            foreach ($check->dependsOn() as $dep) {
                if (isset($checks[$dep])) {
                    $deps[] = $dep;
                    continue;
                }
                foreach ($checks as $id => $candidate) {
                    if ($candidate->category() === $dep && $id !== $check->id()) {
                        $deps[] = $id;
                    }
                }
            }

            return $deps;
        };

        $state = [];
        $visit = function (string $id) use (&$visit, &$state, $checks, $expand): void {
            if (($state[$id] ?? null) === 'done') {
                return;
            }
            $this->assertNotSame('visiting', $state[$id] ?? null, "Dependency cycle at [{$id}].");

            $state[$id] = 'visiting';
            foreach ($expand($checks[$id]) as $dep) {
                $visit($dep);
            }
            $state[$id] = 'done';
        };

        foreach (array_keys($checks) as $id) {
            $visit($id);
        }
    }
}
