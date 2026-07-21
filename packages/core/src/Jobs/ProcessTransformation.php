<?php

namespace AdAstra\Jobs;

use AdAstra\Models\Media\Transformation;
use AdAstra\Services\Media\TransformationDriverInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

class ProcessTransformation implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(public readonly int $transformationId)
    {
    }

    public function handle(TransformationDriverInterface $driver): void
    {
        $transformation = Transformation::find($this->transformationId);

        if (!$transformation || !$transformation->isPending()) {
            return;
        }

        try {
            // Driver is responsible for calling markComplete() with full metadata.
            $driver->applySync($transformation);
        } catch (Throwable $e) {
            $transformation->markFailed($e->getMessage());
        }
    }
}
