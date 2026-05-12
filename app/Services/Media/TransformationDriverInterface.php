<?php

namespace App\Services\Media;

use App\Models\Media\Transformation;

interface TransformationDriverInterface
{
    public function dispatch(Transformation $transformation): void;

    public function applySync(Transformation $transformation): string;
}
