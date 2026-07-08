<?php

namespace Tests\Unit\Doctor\Checks;

use AdAstra\Doctor\Checks\Media\TransformationDriverCheck;
use AdAstra\Doctor\DoctorStatus;
use AdAstra\Models\Media\Transformation;
use AdAstra\Services\Media\NullTransformationDriver;
use AdAstra\Services\Media\TransformationDriverInterface;
use Tests\TestCase;

class TransformationDriverCheckTest extends TestCase
{
    public function test_warns_when_null_driver_is_bound(): void
    {
        $this->app->bind(TransformationDriverInterface::class, fn () => new NullTransformationDriver());

        $results = iterator_to_array((new TransformationDriverCheck())->run(), false);

        $this->assertSame(DoctorStatus::Warn, $results[0]->status);
        $this->assertNotNull($results[0]->fixCommand);
    }

    public function test_passes_with_a_real_driver(): void
    {
        $this->app->bind(TransformationDriverInterface::class, fn () => new class () implements TransformationDriverInterface {
            public function dispatch(Transformation $transformation): void
            {
            }

            public function applySync(Transformation $transformation): string
            {
                return '';
            }
        });

        $results = iterator_to_array((new TransformationDriverCheck())->run(), false);

        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }
}
