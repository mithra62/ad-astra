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

    public function test_required_tables_accounts_for_every_package_migration(): void
    {
        // Tables a stock install creates that doctor deliberately does NOT
        // require. Adding a table here is a conscious decision, not a default:
        // the inclusion test for required_tables is "does an AdAstra feature
        // break without it" (see config/doctor.php).
        $intentionallyExcluded = [
            'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', // framework plumbing
            'fieldables',         // no runtime references yet (dead table)
            'user_oauth_tokens',  // OAuth-only installs
        ];

        // Assumes Schema::create('literal') — true for every current
        // migration; a dynamically named table would slip past this scan.
        $created = [];
        foreach (glob(__DIR__ . '/../../../database/migrations/*.php') as $file) {
            preg_match_all("/Schema::create\('([^']+)'/", file_get_contents($file), $matches);
            $created = array_merge($created, $matches[1]);
        }
        $this->assertNotEmpty($created, 'Migration scan found no tables — path or pattern is broken.');

        $unaccounted = array_diff($created, config('doctor.required_tables'), $intentionallyExcluded);

        $this->assertSame(
            [],
            array_values($unaccounted),
            'New package table(s) not covered by doctor: add them to doctor.required_tables or, deliberately, to $intentionallyExcluded.'
        );
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
