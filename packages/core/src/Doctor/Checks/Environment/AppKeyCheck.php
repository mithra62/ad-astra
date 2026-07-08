<?php

namespace AdAstra\Doctor\Checks\Environment;

use AdAstra\Doctor\AbstractDoctorCheck;

class AppKeyCheck extends AbstractDoctorCheck
{
    protected string $id = 'environment.app-key';
    protected string $name = 'Application key';

    public function run(): iterable
    {
        // Presence only — the key value must never appear in a report.
        if ((string) config('app.key') === '') {
            yield $this->fail(
                'APP_KEY is not set — encrypted data and sessions will not work',
                fixCommand: 'php artisan key:generate',
            );

            return;
        }

        yield $this->pass('APP_KEY exists');
    }
}
