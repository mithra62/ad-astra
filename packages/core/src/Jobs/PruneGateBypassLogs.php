<?php

namespace AdAstra\Jobs;

use AdAstra\Models\GateBypassLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneGateBypassLogs implements ShouldQueue
{
    use Queueable;

    /**
     * Delete gate_bypass_logs rows that fall outside the retention window
     * defined by GateBypassLog::prunable() (settings-driven; 0 = keep forever).
     * Scheduled daily in routes/console.php.
     *
     * To prune immediately from the shell at any time:
     *   php artisan model:prune --model="AdAstra\Models\GateBypassLog"
     */
    public function handle(): void
    {
        (new GateBypassLog())->pruneAll();
    }
}
