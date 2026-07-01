<?php

namespace AdAstra\Services\Media;

use AdAstra\Models\Media\Transformation;
use Illuminate\Support\Facades\Storage;

class GDTransformationDriver implements TransformationDriverInterface
{
    public function dispatch(Transformation $transformation): void
    {
        \AdAstra\Jobs\ProcessTransformation::dispatch($transformation->id);
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
        $media  = $transformation->media;
        $params = $transformation->params ?? [];

        $sourcePath = $this->localPath($media->disk, $media->path);
        $src        = $this->loadImage($sourcePath, $media->mime_type);

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        $targetW  = isset($params['width'])  ? (int)$params['width']  : $srcW;
        $targetH  = isset($params['height']) ? (int)$params['height'] : $srcH;
        $mode     = $params['mode']    ?? 'cover';
        $format   = $params['format']  ?? $this->extensionFromPath($media->path);
        $quality  = isset($params['quality']) ? (int)$params['quality'] : 85;

        $dst = match ($mode) {
            'contain' => $this->applyContain($src, $srcW, $srcH, $targetW, $targetH),
            'exact'   => $this->applyExact($src, $srcW, $srcH, $targetW, $targetH),
            default   => $this->applyCover($src, $srcW, $srcH, $targetW, $targetH),
        };

        imagedestroy($src);

        $outW = imagesx($dst);
        $outH = imagesy($dst);

        $tmpFile = tempnam(sys_get_temp_dir(), 'gd_transform_');

        try {
            $this->saveImage($dst, $tmpFile, $format, $quality);
            imagedestroy($dst);

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

    private function applyCover(\GdImage $src, int $srcW, int $srcH, int $targetW, int $targetH): \GdImage
    {
        $srcRatio = $srcW / $srcH;
        $dstRatio = $targetW / $targetH;

        if ($srcRatio > $dstRatio) {
            // Source is wider — fit on height, crop sides
            $scaledH = $targetH;
            $scaledW = (int)round($targetH * $srcRatio);
            $srcX    = (int)round(($scaledW - $targetW) / 2 * ($srcW / $scaledW));
            $srcY    = 0;
            $srcCropW = (int)round($targetW * ($srcW / $scaledW));
            $srcCropH = $srcH;
        } else {
            // Source is taller — fit on width, crop top/bottom
            $scaledW  = $targetW;
            $scaledH  = (int)round($targetW / $srcRatio);
            $srcX     = 0;
            $srcY     = (int)round(($scaledH - $targetH) / 2 * ($srcH / $scaledH));
            $srcCropW = $srcW;
            $srcCropH = (int)round($targetH * ($srcH / $scaledH));
        }

        $dst = $this->newCanvas($targetW, $targetH);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $targetW, $targetH, $srcCropW, $srcCropH);
        return $dst;
    }

    private function applyContain(\GdImage $src, int $srcW, int $srcH, int $targetW, int $targetH): \GdImage
    {
        $ratio   = min($targetW / $srcW, $targetH / $srcH);
        $newW    = (int)round($srcW * $ratio);
        $newH    = (int)round($srcH * $ratio);

        $dst = $this->newCanvas($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        return $dst;
    }

    private function applyExact(\GdImage $src, int $srcW, int $srcH, int $targetW, int $targetH): \GdImage
    {
        $dst = $this->newCanvas($targetW, $targetH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);
        return $dst;
    }

    // -------------------------------------------------------------------------
    // GD helpers
    // -------------------------------------------------------------------------

    private function newCanvas(int $w, int $h): \GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        // Preserve transparency for PNG/GIF output.
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefilledrectangle($img, 0, 0, $w, $h, $transparent);
        imagealphablending($img, true);
        return $img;
    }

    private function loadImage(string $path, ?string $mimeType): \GdImage
    {
        $img = match (true) {
            str_contains((string)$mimeType, 'png')  => imagecreatefrompng($path),
            str_contains((string)$mimeType, 'gif')  => imagecreatefromgif($path),
            str_contains((string)$mimeType, 'webp') => imagecreatefromwebp($path),
            default                                  => imagecreatefromjpeg($path),
        };

        if ($img === false) {
            throw new \RuntimeException("GD could not load image: {$path}");
        }

        return $img;
    }

    private function saveImage(\GdImage $img, string $path, string $format, int $quality): void
    {
        $ok = match (strtolower($format)) {
            'png'  => imagepng($img, $path, (int)round((100 - $quality) / 10)),
            'gif'  => imagegif($img, $path),
            'webp' => imagewebp($img, $path, $quality),
            default => imagejpeg($img, $path, $quality),
        };

        if ($ok === false) {
            throw new \RuntimeException("GD could not save image to: {$path}");
        }
    }

    private function extensionFromPath(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
    }

    private function localPath(string $disk, string $path): string
    {
        $fullPath = Storage::disk($disk)->path($path);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Source file not found on disk '{$disk}': {$path}");
        }

        return $fullPath;
    }
}
