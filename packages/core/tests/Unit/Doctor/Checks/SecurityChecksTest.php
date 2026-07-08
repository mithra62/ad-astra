<?php

namespace Tests\Unit\Doctor\Checks;

use AdAstra\Doctor\Checks\Security\DevAccountCheck;
use AdAstra\Doctor\DoctorStatus;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_outside_production(): void
    {
        $results = iterator_to_array((new DevAccountCheck())->run(), false);

        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_warns_when_dev_account_exists_in_production(): void
    {
        config(['app.default_dev_email' => 'doctor-dev@example.test']);
        User::factory()->create(['email' => 'doctor-dev@example.test']);
        $this->app['env'] = 'production';

        $results = iterator_to_array((new DevAccountCheck())->run(), false);

        $this->assertSame(DoctorStatus::Warn, $results[0]->status);
        // Presence only — the address itself must not leak into the report.
        $this->assertStringNotContainsString('doctor-dev@example.test', $results[0]->message . $results[0]->details);
    }

    public function test_passes_in_production_without_dev_account(): void
    {
        config(['app.default_dev_email' => 'doctor-dev@example.test']);
        $this->app['env'] = 'production';

        $results = iterator_to_array((new DevAccountCheck())->run(), false);

        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }
}
