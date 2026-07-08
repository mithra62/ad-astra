<?php

namespace Tests\Unit\Doctor\Checks;

use AdAstra\Doctor\Checks\Database\ConnectionCheck;
use AdAstra\Doctor\Checks\Database\PendingMigrationsCheck;
use AdAstra\Doctor\Checks\Database\RequiredTablesCheck;
use AdAstra\Doctor\DoctorStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_connection_passes_against_test_database(): void
    {
        $results = iterator_to_array((new ConnectionCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_connection_fails_on_unresolvable_connection(): void
    {
        $original = config('database.default');
        config(['database.default' => 'doctor-test-bogus']);

        $results = iterator_to_array((new ConnectionCheck())->run(), false);

        // Restore before teardown — RefreshDatabase rolls back on the default connection.
        config(['database.default' => $original]);

        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
        // Details carry the exception class only, never a message that could
        // embed host or username fragments.
        $this->assertStringNotContainsString(' ', (string) $results[0]->details);
    }

    public function test_required_tables_pass_on_migrated_database(): void
    {
        $results = iterator_to_array((new RequiredTablesCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_required_tables_fail_per_missing_table(): void
    {
        config(['doctor.required_tables' => ['users', 'doctor_missing_table']]);

        $results = iterator_to_array((new RequiredTablesCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
        $this->assertStringContainsString('doctor_missing_table', $results[0]->message);
        $this->assertSame('php artisan migrate', $results[0]->fixCommand);
    }

    public function test_pending_migrations_pass_on_fully_migrated_database(): void
    {
        $results = iterator_to_array((new PendingMigrationsCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }
}
