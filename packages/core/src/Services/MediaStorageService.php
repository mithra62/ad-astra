<?php

namespace AdAstra\Services;

use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaStorageService
{
    public function upload(Library $library, UploadedFile $file, array $attributes = []): Media
    {
        return $library->addMediaFromUpload($file, $attributes);
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

    /** Soft-delete — physical file preserved until the purge job runs. */
    public function delete(Media $media): void
    {
        $media->delete();
    }

    public function disk(Media $media): Filesystem
    {
        return Storage::disk($media->disk);
    }

    public function url(Media $media, ?int $signedMinutes = null): string
    {
        return $signedMinutes !== null
            ? $media->temporaryUrl($signedMinutes)
            : $media->url();
    }
}
