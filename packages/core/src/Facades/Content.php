<?php

namespace AdAstra\Facades;

use AdAstra\Builders\EntryQueryBuilder;
use AdAstra\Models\Entry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Entry create(string $typeHandle, array $data = [])
 * @method static Entry update(Entry $entry, array $data = [])
 * @method static Entry get(int $id)
 * @method static Entry|null find(int $id)
 * @method static EntryQueryBuilder query()
 *
 * @see \AdAstra\Services\ContentService
 */
class Content extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AdAstra\Services\ContentService::class;
    }
}
