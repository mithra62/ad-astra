<?php

namespace App\Facades;

use App\Builders\EntryQueryBuilder;
use App\Models\Entry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Entry create(string $typeHandle, array $data = [])
 * @method static Entry get(int $id)
 * @method static Entry|null find(int $id)
 * @method static EntryQueryBuilder query()
 *
 * @see \App\Services\ContentService
 */
class Content extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\ContentService::class;
    }
}
