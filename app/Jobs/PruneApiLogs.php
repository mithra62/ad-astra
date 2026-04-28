<?php

namespace App\Jobs;

use App\Models\ApiLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneApiLogs implements ShouldQueue
{
    use Queueable;

    /**
     * Delete api_logs rows that fall outside the retention window defined by
     * ApiLog::prunable(), then re-queue this job for 02:00 tomorrow so it
     * perpetuates without a cron entry.
     *
     * Initial kickoff (run once after deployment):
     *   php artisan tinker --execute="App\Jobs\PruneApiLogs::dispatch()"
     *
     * To prune immediately from the shell at any time (does not affect the
     * queue chain):
     *   php artisan model:prune --model="App\Models\ApiLog"
     */
    public function handle(): void
    {
        ApiLog::pruneAll();

        // Re-queue for 02:00 tomorrow regardless of when this run started,
        // keeping the execution window consistent day-to-day.
        static::dispatch()->delay(now()->addDay()->setTime(2, 0));
    }
}
