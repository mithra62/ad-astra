<?php

namespace App\Facades;

use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\FieldLayout;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Category      create(CategoryGroup|int $group, array $data)
 * @method static Category      update(Category $category, array $data)
 * @method static bool          delete(Category $category)
 * @method static Category      move(Category $category, ?int $parentId, int $sortOrder = 0)
 *
 * @method static void          setField(Category $category, string $slug, mixed $value)
 * @method static void          setFields(Category $category, array $fields)
 * @method static array         fieldArray(Category $category)
 *
 * @method static FieldLayout|null  resolveLayout(Category $category)
 * @method static Collection        resolveFieldGroups(Category $category)
 * @method static Collection        resolveFields(Category $category)
 *
 * @method static Collection    tree(CategoryGroup|int $group)
 * @method static Collection    flat(CategoryGroup|int $group)
 *
 * @see \App\Services\CategoryService
 */
class Categories extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\CategoryService::class;
    }
}
