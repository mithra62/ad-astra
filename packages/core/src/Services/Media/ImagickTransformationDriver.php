<?php

namespace AdAstra\Services\Media;

use AdAstra\Jobs\ProcessTransformation;
use AdAstra\Models\Media\Transformation;
use Illuminate\Support\Facades\Storage;
use Imagick;
use RuntimeException;

class ImagickTransformationDriver implements TransformationDriverInterface
{
    public function dispatch(Transformation $transformation): void
    {
        ProcessTransformation::dispatch($transformation->id);
    }

    /**
     * Execute the transformation synchronously and mark the record complete.
     *
     * Params (all optional):
     *   width   int    Target width in pixels.
     *   height  int    Target height in pixels.
     *   mode    string 'cover' (default) | 'contain' | 'exact'
     *   format  string 'jpg' | 'png' | 'gif' | 'webp'  (defaults to source extension)
     *   quality int    JPEG/WebP quality 0–100 (default 85)
     */
    public function applySync(Transformation $transformation): string
    {
        $media = $transformation->media;
        $params = $transformation->params ?? [];

        $sourcePath = $this->localPath($media->disk, $media->path);

        $imagick = new Imagick($sourcePath);

        // Honour EXIF orientation before any geometry operations.
        $imagick->autoOrient();

        $srcW = $imagick->getImageWidth();
        $srcH = $imagick->getImageHeight();

        $targetW = isset($params['width']) ? (int)$params['width'] : $srcW;
        $targetH = isset($params['height']) ? (int)$params['height'] : $srcH;
        $mode = $params['mode'] ?? 'cover';
        $format = $params['format'] ?? $this->extensionFromPath($media->path);
        $quality = isset($params['quality']) ? (int)$params['quality'] : 85;

        match ($mode) {
            'contain' => $this->applyContain($imagick, $targetW, $targetH),
            'exact' => $this->applyExact($imagick, $targetW, $targetH),
            default => $this->applyCover($imagick, $targetW, $targetH),
        };

        $outW = $imagick->getImageWidth();
        $outH = $imagick->getImageHeight();

        $imagick->setImageFormat($this->imagickFormat($format));
        $imagick->setImageCompressionQuality($quality);

        // Strip metadata (EXIF, ICC profiles) to reduce output size.
        $imagick->stripImage();

        $tmpFile = tempnam(sys_get_temp_dir(), 'imagick_transform_');

        try {
            $imagick->writeImage($tmpFile);
            $imagick->destroy();

            $size = filesize($tmpFile);
            Storage::disk($transformation->disk)->put(
                $transformation->path,
                file_get_contents($tmpFile)
            );
        } finally {
            @unlink($tmpFile);
        }

        $transformation->markComplete($transformation->path, $size, $outW, $outH);

        return $transformation->path;
    }

    // -------------------------------------------------------------------------
    // Resize modes
    // -------------------------------------------------------------------------

    private function applyCover(Imagick $imagick, int $targetW, int $targetH): void
    {
        // cropThumbnailImage scales to fill the box and crops the excess from centre.
        $imagick->cropThumbnailImage($targetW, $targetH);
    }

    private function applyContain(Imagick $imagick, int $targetW, int $targetH): void
    {
        // thumbnailImage with bestFit=true scales to fit within the box,
        // preserving aspect ratio without adding padding.
        $imagick->thumbnailImage($targetW, $targetH, true);
    }

    private function applyExact(Imagick $imagick, int $targetW, int $targetH): void
    {
        $imagick->resizeImage($targetW, $targetH, Imagick::FILTER_LANCZOS, 1);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function imagickFormat(string $format): string
    {
        return match (strtolower($format)) {
            'jpg' => 'jpeg',
            'webp' => 'webp',
            'png' => 'png',
            'gif' => 'gif',
            default => 'jpeg',
        };
    }

    private function extensionFromPath(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
    }

    private function localPath(string $disk, string $path): string
    {
        $fullPath = Storage::disk($disk)->path($path);

        if (!file_exists($fullPath)) {
            throw new RuntimeException("Source file not found on disk '{$disk}': {$path}");
        }

        return $fullPath;
    }
}
