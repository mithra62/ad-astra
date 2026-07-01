<?php

namespace AdAstra\Services\Media;

use AdAstra\Models\Media\Transformation;

interface TransformationDriverInterface
{
    public function dispatch(Transformation $transformation): void;

    public function applySync(Transformation $transformation): string;
}
