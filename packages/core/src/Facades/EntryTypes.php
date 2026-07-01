<?php

namespace AdAstra\Facades;

use AdAstra\Models\EntryType;
use Illuminate\Support\Facades\Facade;

/**
 * @method static EntryType create(array $data)
 * @method static EntryType update(EntryType $type, array $data)
 * @method static bool delete(EntryType $type)
 * @method static EntryType|null find(int $id)
 * @method static EntryType get(int $id)
 *
 * @see \AdAstra\Services\EntryTypeService
 */
class EntryTypes extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AdAstra\Services\EntryTypeService::class;
    }
}
