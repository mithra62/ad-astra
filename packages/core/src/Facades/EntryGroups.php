<?php

namespace AdAstra\Facades;

use AdAstra\Models\EntryGroup;
use AdAstra\Services\EntryGroupService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static EntryGroup create(array $data)
 * @method static EntryGroup update(EntryGroup $group, array $data)
 * @method static bool delete(EntryGroup $group)
 * @method static EntryGroup|null find(int $id)
 * @method static EntryGroup get(int $id)
 *
 * @see EntryGroupService
 */
class EntryGroups extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EntryGroupService::class;
    }
}
