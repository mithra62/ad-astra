<?php

namespace AdAstra\Facades;

use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use AdAstra\Services\MediaStorageService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Media upload(Library $library, UploadedFile $file, array $attributes = [])
 * @method static void delete(Media $media)
 * @method static void purge(Media $media)
 * @method static string url(Media $media, ?int $signedMinutes = null)
 * @method static Filesystem disk(Media $media)
 *
 * @see MediaStorageService
 */
class MediaStorage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'media-service';
    }
}
