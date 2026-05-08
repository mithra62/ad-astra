<?php

namespace App\Traits;

use App\Models\Media\Transformation;
use App\Services\Media\TransformationDriverInterface;
use Illuminate\Support\Facades\Storage;

trait HasTransformations
{
    public function getTransformation(string $key): ?Transformation
    {
        return $this->transformations()->where('key', $key)->first();
    }

    /** Returns the transformation only if it completed successfully. */
    public function transformation(string $key): ?Transformation
    {
        $t = $this->getTransformation($key);
        return ($t && $t->isComplete()) ? $t : null;
    }

    public function hasTransformation(string $key): bool
    {
        return $this->transformation($key) !== null;
    }

    public function transform(string $key, array $params = []): Transformation
    {
        $existing = $this->getTransformation($key);

        if ($existing && $existing->isComplete()) {
            return $existing;
        }

        $transformation = $existing ?? $this->transformations()->create([
            'key'    => $key,
            'disk'   => $this->disk,
            'path'   => $this->derivedPath($key, $params),
            'params' => $params,
            'status' => 'pending',
        ]);

        app(TransformationDriverInterface::class)->dispatch($transformation);

        return $transformation;
    }

    public function clearTransformation(string $key): void
    {
        $t = $this->getTransformation($key);
        if (!$t) {
            return;
        }
        if ($t->fileExists()) {
            Storage::disk($t->disk)->delete($t->path);
        }
        $t->delete();
    }

    public function clearTransformations(): void
    {
        foreach ($this->transformations as $t) {
            if ($t->fileExists()) {
                Storage::disk($t->disk)->delete($t->path);
            }
            $t->delete();
        }
    }

    protected function derivedPath(string $key, array $params = []): string
    {
        $dir  = dirname($this->path);
        $stem = pathinfo($this->file_name, PATHINFO_FILENAME);
        $ext  = $params['format'] ?? pathinfo($this->file_name, PATHINFO_EXTENSION);
        return $dir . '/_t/' . $stem . '_' . $key . '.' . $ext;
    }
}
