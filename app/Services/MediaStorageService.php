<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaStorageService
{
    public function upload(Library $library, UploadedFile $file, array $attributes = []): Media
    {
        return $library->addMediaFromUpload($file, $attributes);
    }

    /** Soft-delete — physical file preserved until the purge job runs. */
    public function delete(Media $media): void
    {
        $media->delete();
    }

    /** Hard-delete record and physical file immediately. */
    public function purge(Media $media): void
    {
        foreach ($media->transformations as $t) {
            Storage::disk($t->disk)->delete($t->path);
        }
        Storage::disk($media->disk)->delete($media->path);
        $media->forceDelete();
    }

    public function url(Media $media, ?int $signedMinutes = null): string
    {
        return $signedMinutes !== null
            ? $media->temporaryUrl($signedMinutes)
            : $media->url();
    }

    public function disk(Media $media): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk($media->disk);
    }
}
