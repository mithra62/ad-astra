<?php

namespace AdAstra\Doctor\Checks\Database;

use AdAstra\Doctor\AbstractDoctorCheck;
use Illuminate\Database\Migrations\Migrator;

class StrayMigrationsCheck extends AbstractDoctorCheck
{
    protected string $id = 'database.stray-migrations';
    protected string $name = 'Stray migrations';

    public function dependsOn(): array
    {
        return ['database.connection'];
    }

    public function run(): iterable
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        if (!$migrator->repositoryExists()) {
            // pending-migrations already fails on a never-migrated database.
            yield $this->skip('No migrations table to inspect');

            return;
        }

        $files = $migrator->getMigrationFiles(
            array_merge($migrator->paths(), [database_path('migrations')])
        );

        // The inverse of pending: rows recorded as run whose file no longer
        // exists — a version mismatch or rollback debt.
        $stray = array_diff($migrator->getRepository()->getRan(), array_keys($files));

        foreach ($stray as $migration) {
            yield $this->warn(
                "Migration [{$migration}] has run but its file is missing",
                details: 'Usually a downgraded package or a renamed/deleted migration file',
            );
        }

        if ($stray === []) {
            yield $this->pass('No stray migrations');
        }
    }
}
