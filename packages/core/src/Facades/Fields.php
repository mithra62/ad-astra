<?php

namespace AdAstra\Facades;

use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Services\FieldService;
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
