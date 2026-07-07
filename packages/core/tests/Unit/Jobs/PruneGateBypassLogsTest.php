<?php

namespace Tests\Unit\Jobs;

use AdAstra\Jobs\PruneGateBypassLogs;
use AdAstra\Models\GateBypassLog;
use AdAstra\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PruneGateBypassLogsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Default retention (365 days)
    // -------------------------------------------------------------------------

    public function test_logs_within_default_retention_window_are_kept(): void
    {
        $log = $this->bypassLog(daysAgo: 300);

        (new PruneGateBypassLogs())->handle();

        $this->assertDatabaseHas('gate_bypass_logs', ['id' => $log->id]);
    }

    public function test_logs_outside_default_retention_window_are_deleted(): void
    {
        $log = $this->bypassLog(daysAgo: 366);

        (new PruneGateBypassLogs())->handle();

        $this->assertDatabaseMissing('gate_bypass_logs', ['id' => $log->id]);
    }

    // -------------------------------------------------------------------------
    // Settings-driven retention
    // -------------------------------------------------------------------------

    public function test_retention_setting_shortens_the_window(): void
    {
        app(Settings::class)->set('security', 'gate_bypass_log_retention_days', 30);

        $old = $this->bypassLog(daysAgo: 31);
        $recent = $this->bypassLog(daysAgo: 10);

        (new PruneGateBypassLogs())->handle();

        $this->assertDatabaseMissing('gate_bypass_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('gate_bypass_logs', ['id' => $recent->id]);
    }

    public function test_zero_retention_means_keep_forever(): void
    {
        app(Settings::class)->set('security', 'gate_bypass_log_retention_days', 0);

        $ancient = $this->bypassLog(daysAgo: 5000);

        (new PruneGateBypassLogs())->handle();

        $this->assertDatabaseHas('gate_bypass_logs', ['id' => $ancient->id]);
    }

    // -------------------------------------------------------------------------
    // No self-rescheduling
    // -------------------------------------------------------------------------

    public function test_handle_does_not_dispatch_another_job(): void
    {
        Queue::fake();

        (new PruneGateBypassLogs())->handle();

        Queue::assertNothingPushed();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function bypassLog(int $daysAgo): GateBypassLog
    {
        $log = GateBypassLog::factory()->create();

        DB::table('gate_bypass_logs')
            ->where('id', $log->id)
            ->update(['created_at' => now()->subDays($daysAgo)]);

        return $log;
    }
}
