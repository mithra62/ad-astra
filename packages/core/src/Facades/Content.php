<?php

namespace AdAstra\Facades;

use AdAstra\Builders\EntryQueryBuilder;
use AdAstra\Models\Entry;
use AdAstra\Services\ContentService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Entry create(string $typeHandle, array $data = [])
 * @method static Entry update(Entry $entry, array $data = [])
 * @method static Entry get(int $id)
 * @method static Entry|null find(int $id)
 * @method static EntryQueryBuilder query()
 *
 * @see ContentService
 */
class Content extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ContentService::class;
    }
}
