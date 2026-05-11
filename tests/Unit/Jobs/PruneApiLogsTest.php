<?php

namespace Tests\Unit\Jobs;

use App\Jobs\PruneApiLogs;
use App\Models\ApiLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneApiLogsTest extends TestCase
{
    use RefreshDatabase;

    private function apiLog(int $daysAgo): ApiLog
    {
        $log = ApiLog::factory()->create();

        DB::table('api_logs')
            ->where('id', $log->id)
            ->update(['created_at' => now()->subDays($daysAgo)]);

        return $log;
    }

    // -------------------------------------------------------------------------
    // Retention window
    // -------------------------------------------------------------------------

    public function test_logs_within_retention_window_are_kept(): void
    {
        $log = $this->apiLog(daysAgo: 30); // inside the 90-day window

        (new PruneApiLogs)->handle();

        $this->assertDatabaseHas('api_logs', ['id' => $log->id]);
    }

    public function test_logs_outside_retention_window_are_deleted(): void
    {
        $log = $this->apiLog(daysAgo: 91); // past the 90-day window

        (new PruneApiLogs)->handle();

        $this->assertDatabaseMissing('api_logs', ['id' => $log->id]);
    }

    public function test_logs_exactly_at_boundary_are_kept(): void
    {
        $log = $this->apiLog(daysAgo: 90);

        (new PruneApiLogs)->handle();

        $this->assertDatabaseHas('api_logs', ['id' => $log->id]);
    }

    public function test_only_old_logs_are_pruned_leaving_recent_ones_intact(): void
    {
        $old    = $this->apiLog(daysAgo: 91);
        $recent = $this->apiLog(daysAgo: 10);

        (new PruneApiLogs)->handle();

        $this->assertDatabaseMissing('api_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('api_logs',    ['id' => $recent->id]);
    }

    // -------------------------------------------------------------------------
    // No self-rescheduling
    // -------------------------------------------------------------------------

    public function test_handle_does_not_dispatch_another_job(): void
    {
        Queue::fake();

        (new PruneApiLogs)->handle();

        Queue::assertNothingPushed();
    }
}
