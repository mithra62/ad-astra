<?php

namespace AdAstra\Doctor\Checks\Environment;

use AdAstra\Doctor\AbstractDoctorCheck;

class PhpVersionCheck extends AbstractDoctorCheck
{
    /**
     * Mirrors the `php` constraint in composer.json (^8.2).
     */
    public const MINIMUM = '8.2.0';

    protected string $id = 'environment.php-version';
    protected string $name = 'PHP version';

    public function run(): iterable
    {
        if (version_compare(PHP_VERSION, self::MINIMUM, '<')) {
            yield $this->fail(
                'PHP ' . PHP_VERSION . ' is below the required minimum ' . self::MINIMUM,
                fixCommand: 'upgrade PHP to ' . self::MINIMUM . ' or later',
            );

            return;
        }

        yield $this->pass('PHP ' . PHP_VERSION);
    }
}
