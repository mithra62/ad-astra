<?php

namespace AdAstra\Doctor\Checks\Environment;

use AdAstra\Doctor\AbstractDoctorCheck;
use Illuminate\Foundation\Application;

class LaravelVersionCheck extends AbstractDoctorCheck
{
    /**
     * Mirrors the `laravel/framework` constraint in composer.json (^12.0).
     */
    public const SUPPORTED_MAJOR = 12;

    protected string $id = 'environment.laravel-version';
    protected string $name = 'Laravel version';

    public function run(): iterable
    {
        $major = (int) explode('.', Application::VERSION)[0];

        if ($major < self::SUPPORTED_MAJOR) {
            yield $this->fail(
                'Laravel ' . Application::VERSION . ' is below the supported major version ' . self::SUPPORTED_MAJOR,
                fixCommand: 'composer update laravel/framework',
            );

            return;
        }

        yield $this->pass('Laravel ' . Application::VERSION);
    }
}
