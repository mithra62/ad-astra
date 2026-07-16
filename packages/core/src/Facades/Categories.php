<?php

namespace AdAstra\Facades;

use AdAstra\Models\Category;
use AdAstra\Models\Category\Group as CategoryGroup;
use AdAstra\Models\FieldLayout;
use AdAstra\Services\CategoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Category create(CategoryGroup|int $group, array $data)
 * @method static Category update(Category $category, array $data)
 * @method static bool delete(Category $category)
 * @method static Category move(Category $category, ?int $parentId, int $sortOrder = 0)
 * @method static void setField(Category $category, string $handle, mixed $value)
 * @method static void setFields(Category $category, array $fields)
 * @method static array fieldArray(Category $category)
 * @method static FieldLayout|null resolveLayout(Category $category)
 * @method static Collection resolveFieldGroups(Category $category)
 * @method static Collection resolveFields(Category $category)
 * @method static Collection tree(CategoryGroup|int $group)
 * @method static Collection flat(CategoryGroup|int $group)
 *
 * @see CategoryService
 */
class Categories extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CategoryService::class;
    }
}
