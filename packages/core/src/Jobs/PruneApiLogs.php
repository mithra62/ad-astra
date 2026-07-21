<?php

namespace AdAstra\Jobs;

use AdAstra\Models\ApiLog;
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
     *   php artisan tinker --execute="AdAstra\Jobs\PruneApiLogs::dispatch()"
     *
     * To prune immediately from the shell at any time (does not affect the
     * queue chain):
     *   php artisan model:prune --model="AdAstra\Models\ApiLog"
     */
    public function handle(): void
    {
        (new ApiLog())->pruneAll();
    }
}
