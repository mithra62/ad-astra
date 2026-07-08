<?php

namespace AdAstra\Doctor\Checks\Media;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\Services\Media\NullTransformationDriver;
use AdAstra\Services\Media\TransformationDriverInterface;

class TransformationDriverCheck extends AbstractDoctorCheck
{
    protected string $id = 'media.transformation-driver';
    protected string $name = 'Image transformation driver';

    public function run(): iterable
    {
        // Resolve the actual container binding rather than re-testing
        // extension_loaded() — this also honors host-app rebinding.
        $driver = app(TransformationDriverInterface::class);

        if ($driver instanceof NullTransformationDriver) {
            yield $this->warn(
                'No image extension loaded — media transformations will silently do nothing',
                details: 'The container fell back to the null transformation driver',
                fixCommand: 'install/enable the imagick or gd PHP extension',
            );

            return;
        }

        yield $this->pass('Transformation driver: ' . class_basename($driver));
    }
}
