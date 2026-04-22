<?php

namespace App\Facades;

use App\Builders\EntryQueryBuilder;
use App\Models\Entry;
use App\Models\FieldLayout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Entry create(string $typeHandle, array $data = [])
 * @method static Entry update(Entry $entry, array $data = [])
 * @method static bool delete(Entry $entry)
 * @method static Entry get(int $id)
 * @method static Entry|null find(int $id)
 * @method static EntryQueryBuilder query()
 * @method static array fieldArray(Entry $entry)
 * @method static FieldLayout|null resolveLayout(Entry $entry)
 * @method static Collection resolveFields(Entry $entry)
 *
 * @see \App\Services\EntryService
 */
class Entries extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\EntryService::class;
    }
}
