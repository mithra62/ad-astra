<?php

namespace App\Facades;

use App\Models\Media;
use App\Models\Media\Library;
use App\Services\MediaStorageService;
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
