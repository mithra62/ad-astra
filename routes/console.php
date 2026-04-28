<?php

use App\Models\ApiLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prune api_logs rows older than the configured retention window.
// Runs daily at 02:00 to avoid peak traffic hours.
// Requires the Laravel scheduler cron to be active on the server:
//   * * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('model:prune', ['--model' => [ApiLog::class]])
    ->dailyAt('02:00')
    ->runInBackground();
