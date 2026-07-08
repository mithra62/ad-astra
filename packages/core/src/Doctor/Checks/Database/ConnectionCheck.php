<?php

namespace AdAstra\Doctor\Checks\Database;

use AdAstra\Doctor\AbstractDoctorCheck;
use Illuminate\Support\Facades\DB;
use Throwable;

class ConnectionCheck extends AbstractDoctorCheck
{
    protected string $id = 'database.connection';
    protected string $name = 'Database connection';

    public function run(): iterable
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            // Exception class only — PDO messages can embed host/username,
            // and reports must be safe to paste into public issues.
            yield $this->fail(
                'Cannot connect to the database',
                details: get_class($e),
                fixCommand: 'verify the DB_* settings in .env',
            );

            return;
        }

        yield $this->pass('Connected (' . DB::connection()->getDriverName() . ')');
    }
}
