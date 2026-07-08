<?php

namespace AdAstra\Doctor\Checks\Environment;

use AdAstra\Doctor\AbstractDoctorCheck;
use Illuminate\Support\Str;

class AppUrlCheck extends AbstractDoctorCheck
{
    protected string $id = 'environment.app-url';
    protected string $name = 'Application URL';

    public function run(): iterable
    {
        if (!app()->environment('production')) {
            yield $this->pass('APP_URL not enforced outside production');

            return;
        }

        $url = (string) config('app.url');

        if ($url === '') {
            yield $this->warn(
                'APP_URL is not set — generated URLs and signed routes will be wrong',
                fixCommand: 'set APP_URL in .env',
            );

            return;
        }

        if (Str::contains($url, ['localhost', '127.0.0.1'])) {
            yield $this->warn(
                'APP_URL points at localhost in production',
                fixCommand: 'set APP_URL to the public site URL in .env',
            );

            return;
        }

        if (!Str::startsWith($url, 'https://')) {
            yield $this->warn(
                'APP_URL is not HTTPS in production — secure cookies and generated links may break',
                fixCommand: 'set APP_URL to an https:// URL in .env',
            );

            return;
        }

        yield $this->pass('APP_URL looks production-ready');
    }
}
