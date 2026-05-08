<?php

namespace App\Services\Media;

use App\Models\Media\Transformation;

class NullTransformationDriver implements TransformationDriverInterface
{
    public function dispatch(Transformation $transformation): void
    {
        $transformation->markFailed('No transformation driver configured.');
    }

    public function applySync(Transformation $transformation): string
    {
        throw new \RuntimeException('No transformation driver configured.');
    }

    public function resize(int $width, int $height): static
    {
        return $this;
    }

    public function fit(int $width, int $height): static
    {
        return $this;
    }

    public function crop(int $width, int $height, int $x = 0, int $y = 0): static
    {
        return $this;
    }

    public function quality(int $quality): static
    {
        return $this;
    }

    public function format(string $format): static
    {
        return $this;
    }

    public function sharpen(int $amount = 10): static
    {
        return $this;
    }

    public function watermark(string $sourcePath, string $position = 'bottom-right'): static
    {
        return $this;
    }
}
