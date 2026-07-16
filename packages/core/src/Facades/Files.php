<?php

namespace AdAstra\Facades;

use AdAstra\Services\FilesService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array getAllowedMimeTypes()
 * @method static string compileMimeTypes(array $mime_types)
 * @method static float convertMbToBytes(float $mb_value)
 *
 * @see FilesService
 */
class Files extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'files-service';
    }
}
