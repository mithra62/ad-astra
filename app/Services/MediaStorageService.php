<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Media\Library;
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
        $media->library->removeMedia($media);
    }

    /** Hard-delete record and physical file immediately. */
    public function purge(Media $media): void
    {
        $media->library->purgeMedia($media);
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
