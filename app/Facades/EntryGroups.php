<?php

namespace App\Facades;

use App\Models\EntryGroup;
use Illuminate\Support\Facades\Facade;

/**
 * @method static EntryGroup create(array $data)
 * @method static EntryGroup update(EntryGroup $group, array $data)
 * @method static bool delete(EntryGroup $group)
 * @method static EntryGroup|null find(int $id)
 * @method static EntryGroup get(int $id)
 *
 * @see \App\Services\EntryGroupService
 */
class EntryGroups extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\EntryGroupService::class;
    }
}
