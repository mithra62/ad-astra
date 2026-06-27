<?php

namespace App\Facades;

use App\Models\Field\Type as FieldType;
use App\Services\FieldService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array getFieldOptions()
 * @method static Collection getAllFieldTypes()
 * @method static FieldType|null getFieldType(string $handle)
 *
 * @see FieldService
 */
class Fields extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'fields-service';
    }
}
