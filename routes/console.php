<?php

use App\Jobs\PruneApiLogs;
use App\Jobs\PurgeDeletedMedia;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires the Laravel scheduler cron to be active on the server:
//   * * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1

// Prune api_logs rows older than the configured retention window.
Schedule::job(new PruneApiLogs)->dailyAt('02:00');

// Permanently remove soft-deleted media (and their physical files) after the
// grace period. Grace period defaults to 30 days.
Schedule::job(new PurgeDeletedMedia(graceDays: 30))->dailyAt('03:00');
