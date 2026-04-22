<?php

namespace App\Services;

use App\Concerns\PersistsFieldValues;
use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\FieldLayout;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class CategoryService extends AbstractService
{
    use PersistsFieldValues;

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a category within a group, optionally nested under a parent.
     *
     * Accepted keys in $data:
     *   name, slug, sort_order   — core category attributes
     *   parent_id (int|null)     — parent category ID for nested categories
     *   fields (array)           — ['slug' => value] custom field values
     */
    public function create(CategoryGroup|int $group, array $data): Category
    {
        $groupId    = $group instanceof CategoryGroup ? $group->getKey() : $group;
        $attributes = Arr::except($data, ['fields']);

        $category = Category::create(array_merge($attributes, ['group_id' => $groupId]));

        if (array_key_exists('fields', $data) && is_array($data['fields'])) {
            $this->setFields($category, $data['fields']);
        }

        return $category->refresh();
    }

    /**
     * Update a category's core attributes and/or custom fields.
     * Only keys present in $data are touched.
     */
    public function update(Category $category, array $data): Category
    {
        $attributes = Arr::except($data, ['fields']);

        if (! empty($attributes)) {
            $category->update($attributes);
        }

        if (array_key_exists('fields', $data) && is_array($data['fields'])) {
            $this->setFields($category, $data['fields']);
        }

        return $category->refresh();
    }

    /**
     * Delete a category. Field values cascade via DB constraint.
     */
    public function delete(Category $category): bool
    {
        return (bool) $category->delete();
    }

    /**
     * Move a category to a new parent (or promote to root) and set sort order.
     */
    public function move(Category $category, ?int $parentId, int $sortOrder = 0): Category
    {
        $category->update([
            'parent_id'  => $parentId,
            'sort_order' => $sortOrder,
        ]);

        return $category->refresh();
    }

    /**
     * Return the category's current field values as ['slug' => resolvedValue].
     */
    public function fieldArray(Category $category): array
    {
        $category->loadMissing('fieldValues');

        return $category->fieldArray();
    }

    // -------------------------------------------------------------------------
    // Schema Resolution
    //
    // Fields, layouts, and groups are owned by the CategoryGroup — not by
    // individual categories. These methods resolve the schema that applies to
    // a given category based on the group it belongs to.
    // -------------------------------------------------------------------------

    /**
     * Resolve the FieldLayout for the category's group.
     * Returns null if the group has no layout assigned.
     */
    public function resolveLayout(Category $category): ?FieldLayout
    {
        return $this->loadGroup($category)?->fieldLayout;
    }

    /**
     * Resolve the FieldGroups attached to the category's group.
     */
    public function resolveFieldGroups(Category $category): Collection
    {
        return $this->loadGroup($category)?->fieldGroups ?? collect();
    }

    /**
     * Resolve all Field models available to a category via its group's layout,
     * returned in tab/sort_order sequence.
     */
    public function resolveFields(Category $category): Collection
    {
        $layout = $this->resolveLayout($category);

        if (! $layout) {
            return collect();
        }

        $layout->loadMissing('tabs.elements.field');

        return collect($layout->fields());
    }

    // -------------------------------------------------------------------------
    // Querying
    // -------------------------------------------------------------------------

    /**
     * Full recursive tree of categories for a group (roots + all descendants).
     */
    public function tree(CategoryGroup|int $group): Collection
    {
        $groupId = $group instanceof CategoryGroup ? $group->getKey() : $group;

        return Category::inGroup($groupId)
            ->roots()
            ->with('childrenRecursive')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Flat list of all categories in a group.
     */
    public function flat(CategoryGroup|int $group): Collection
    {
        $groupId = $group instanceof CategoryGroup ? $group->getKey() : $group;

        return Category::inGroup($groupId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function loadGroup(Category $category): ?CategoryGroup
    {
        $category->loadMissing('group.fieldLayout', 'group.fieldGroups');

        return $category->group;
    }
}

