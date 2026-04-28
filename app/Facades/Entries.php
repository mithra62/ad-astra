<?php

namespace App\Facades;

use App\Builders\EntryQueryBuilder;
use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryTree;
use App\Models\FieldLayout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Entry create(string $typeHandle, array $data = [])
 * @method static Entry update(Entry $entry, array $data = [])
 * @method static bool delete(Entry $entry)
 * @method static Entry get(int $id)
 * @method static Entry|null find(int $id)
 * @method static Entry|null findMeta(int $id)
 * @method static Entry getMeta(int $id)
 * @method static Entry|null findByHandle(string $handle, string|int|EntryGroup $group)
 * @method static Entry findOrFailByHandle(string $handle, string|int|EntryGroup $group)
 * @method static Collection loadRelatedRecursive(Entry $entry, string $fieldHandle, int $maxDepth = 3, array $seen = [])
 * @method static EntryQueryBuilder query()
 * @method static array fieldArray(Entry $entry)
 * @method static FieldLayout|null resolveLayout(Entry $entry)
 * @method static Collection resolveFields(Entry $entry)
 * @method static EntryTree createTreeNode(Entry $entry, string $handle, ?EntryTree $parent = null, ?string $template = null, bool $isHome = false)
 * @method static EntryTree moveTreeNode(EntryTree $node, ?EntryTree $newParent, int $sortOrder = 0)
 * @method static void rebuildTreeUri(EntryTree $node)
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
