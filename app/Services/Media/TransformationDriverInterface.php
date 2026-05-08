<?php

namespace App\Services\Media;

use App\Models\Media\Transformation;

interface TransformationDriverInterface
{
    public function dispatch(Transformation $transformation): void;
    public function applySync(Transformation $transformation): string;
    public function resize(int $width, int $height): static;
    public function fit(int $width, int $height): static;
    public function crop(int $width, int $height, int $x = 0, int $y = 0): static;
    public function quality(int $quality): static;
    public function format(string $format): static;
    public function sharpen(int $amount = 10): static;
    public function watermark(string $sourcePath, string $position = 'bottom-right'): static;
}
