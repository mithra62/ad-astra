<?php

namespace AdAstra\Doctor\Checks\Database;

use AdAstra\Doctor\AbstractDoctorCheck;
use Illuminate\Database\Migrations\Migrator;

class PendingMigrationsCheck extends AbstractDoctorCheck
{
    protected string $id = 'database.pending-migrations';
    protected string $name = 'Pending migrations';

    public function dependsOn(): array
    {
        return ['database.connection'];
    }

    public function run(): iterable
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        if (!$migrator->repositoryExists()) {
            yield $this->fail(
                'The migrations table does not exist — this database has never been migrated',
                fixCommand: 'php artisan migrate',
            );

            return;
        }

        // Same sources migrate:status reads: paths registered via
        // loadMigrationsFrom() plus the app's own migration directory.
        $files = $migrator->getMigrationFiles(
            array_merge($migrator->paths(), [database_path('migrations')])
        );
        $ran = $migrator->getRepository()->getRan();

        $pending = array_diff(array_keys($files), $ran);

        foreach ($pending as $migration) {
            yield $this->warn(
                "Pending migration [{$migration}]",
                fixCommand: 'php artisan migrate',
            );
        }

        if ($pending === []) {
            yield $this->pass('No pending migrations');
        }
    }
}
