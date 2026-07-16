<?php

namespace AdAstra\Doctor\Checks\Database;

use AdAstra\Doctor\AbstractDoctorCheck;
use Illuminate\Support\Facades\Schema;

class RequiredTablesCheck extends AbstractDoctorCheck
{
    protected string $id = 'database.required-tables';
    protected string $name = 'Required tables';

    public function dependsOn(): array
    {
        return ['database.connection'];
    }

    public function run(): iterable
    {
        $required = (array) config('doctor.required_tables', []);
        $missing = 0;

        foreach ($required as $table) {
            if (!Schema::hasTable($table)) {
                $missing++;
                yield $this->fail(
                    "Missing table [{$table}]",
                    fixCommand: 'php artisan migrate',
                );
            }
        }

        if ($missing === 0) {
            yield $this->pass('All ' . count($required) . ' required tables exist');
        }
    }
}
