<?php

namespace Tests\Unit\Doctor\Checks;

use AdAstra\Doctor\Checks\Environment\AppDebugCheck;
use AdAstra\Doctor\Checks\Environment\AppKeyCheck;
use AdAstra\Doctor\Checks\Environment\AppUrlCheck;
use AdAstra\Doctor\Checks\Environment\LaravelVersionCheck;
use AdAstra\Doctor\Checks\Environment\PhpVersionCheck;
use AdAstra\Doctor\DoctorStatus;
use Tests\TestCase;

class EnvironmentChecksTest extends TestCase
{
    public function test_php_version_passes_on_supported_runtime(): void
    {
        $results = iterator_to_array((new PhpVersionCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
        $this->assertStringContainsString(PHP_VERSION, $results[0]->message);
    }

    public function test_laravel_version_passes_on_supported_runtime(): void
    {
        $results = iterator_to_array((new LaravelVersionCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_app_key_passes_when_set(): void
    {
        $results = iterator_to_array((new AppKeyCheck())->run(), false);

        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
        // Presence only — the key value must never leak into the report.
        $this->assertStringNotContainsString(config('app.key'), $results[0]->message);
    }

    public function test_app_key_fails_when_empty(): void
    {
        config(['app.key' => '']);

        $results = iterator_to_array((new AppKeyCheck())->run(), false);

        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
        $this->assertSame('php artisan key:generate', $results[0]->fixCommand);
    }

    public function test_app_debug_passes_outside_production(): void
    {
        config(['app.debug' => true]);

        $results = iterator_to_array((new AppDebugCheck())->run(), false);

        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_app_debug_warns_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true]);

        $results = iterator_to_array((new AppDebugCheck())->run(), false);

        $this->assertSame(DoctorStatus::Warn, $results[0]->status);
    }

    public function test_app_url_passes_outside_production(): void
    {
        config(['app.url' => 'http://localhost']);

        $results = iterator_to_array((new AppUrlCheck())->run(), false);

        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_app_url_warns_on_localhost_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'http://localhost']);

        $results = iterator_to_array((new AppUrlCheck())->run(), false);

        $this->assertSame(DoctorStatus::Warn, $results[0]->status);
    }

    public function test_app_url_warns_on_plain_http_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'http://example.com']);

        $results = iterator_to_array((new AppUrlCheck())->run(), false);

        $this->assertSame(DoctorStatus::Warn, $results[0]->status);
    }

    public function test_app_url_passes_on_https_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'https://example.com']);

        $results = iterator_to_array((new AppUrlCheck())->run(), false);

        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }
}
