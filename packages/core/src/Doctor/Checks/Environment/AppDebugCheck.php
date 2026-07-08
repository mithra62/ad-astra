<?php

namespace AdAstra\Doctor\Checks\Environment;

use AdAstra\Doctor\AbstractDoctorCheck;

class AppDebugCheck extends AbstractDoctorCheck
{
    protected string $id = 'environment.app-debug';
    protected string $name = 'Debug mode';

    public function run(): iterable
    {
        if (config('app.debug') && app()->environment('production')) {
            yield $this->warn(
                'APP_DEBUG is enabled in production — stack traces and environment details leak to visitors',
                fixCommand: 'set APP_DEBUG=false in .env',
            );

            return;
        }

        yield $this->pass('APP_DEBUG safe for current environment');
    }
}
