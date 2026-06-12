<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\FieldLayout;
use App\Traits\Field\PersistsFieldValues;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupportCollection;

class CategoryService extends AbstractService
{
    use PersistsFieldValues;

    private const MAX_ANCESTOR_DEPTH = 32;

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a category within a group, optionally nested under a parent.
     *
     * Accepted keys in $data:
     *   name, handle, sort_order   — core category attributes
     *   parent_id (int|null)       — parent category ID for nested categories
     *   fields (array)             — ['handle' => value] custom field values
     */
    public function create(CategoryGroup|int $group, array $data): Category
    {
        $groupId = $group instanceof CategoryGroup ? $group->getKey() : $group;
        $attributes = Arr::except($data, ['fields']);

        $category = Category::create(array_merge($attributes, ['group_id' => $groupId]));

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
        return (bool)$category->delete();
    }

    /**
     * Move a category to a new parent (or promote to root) and set sort order.
     *
     * @throws \InvalidArgumentException if the move would create a circular reference
     */
    public function move(Category $category, ?int $parentId, int $sortOrder = 0): Category
    {
        if ($parentId !== null && $this->wouldCreateCycle($category, $parentId)) {
            throw new \InvalidArgumentException(
                "Moving category [{$category->id}] under [{$parentId}] would create a circular reference."
            );
        }

        $category->update([
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
        ]);

        return $category->refresh();
    }

    /**
     * Walk the ancestor chain of $targetParentId and return true if $category
     * appears in it (which would create a cycle after the move).
     *
     * Reads only the `parent_id` column per level — no full model hydration.
     * A visited-ID set prevents an infinite loop when stored data already
     * contains a cycle (MED-09 / corrupt state). The walk is capped at
     * MAX_ANCESTOR_DEPTH levels as a final safety net.
     */
    private function wouldCreateCycle(Category $category, int $targetParentId): bool
    {
        // Direct self-reference: moving $category under itself.
        if ($targetParentId === $category->id) {
            return true;
        }

        $visited  = [$targetParentId => true];
        $current  = $targetParentId;
        $maxDepth = self::MAX_ANCESTOR_DEPTH;

        while ($maxDepth-- > 0) {
            $parentId = Category::where('id', $current)->value('parent_id');

            if ($parentId === null) {
                // Reached a root — $category is not in this ancestor chain.
                return false;
            }

            if ($parentId === $category->id) {
                // $category is an ancestor of $targetParentId: the move would create a cycle.
                return true;
            }

            if (isset($visited[$parentId])) {
                // Pre-existing cycle in stored data — stop to avoid an infinite loop.
                return false;
            }

            $visited[$parentId] = true;
            $current = $parentId;
        }

        // Depth cap reached — treat as no cycle (conservative: allows the move).
        return false;
    }

    /**
     * Update a category's core attributes and/or custom fields.
     * Only keys present in $data are touched.
     */
    public function update(Category $category, array $data): Category
    {
        $attributes = Arr::except($data, ['fields']);

        if (!empty($attributes)) {
            $category->update($attributes);
        }

        if (array_key_exists('fields', $data) && is_array($data['fields'])) {
            $this->setFields($category, $data['fields']);
        }

        return $category->refresh();
    }

    /**
     * Return the category's current field values as ['handle' => resolvedValue].
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
     * Resolve the FieldGroups attached to the category's group.
     */
    public function resolveFieldGroups(Category $category): SupportCollection
    {
        return collect();
    }

    private function loadGroup(Category $category): ?CategoryGroup
    {
        $category->loadMissing('group.fieldLayout');

        return $category->group;
    }

    /**
     * Resolve all Field models available to a category via its group's layout,
     * returned in tab/sort_order sequence.
     */
    public function resolveFields(Category $category): SupportCollection
    {
        $layout = $this->resolveLayout($category);

        if (!$layout) {
            return collect();
        }

        $layout->loadMissing('tabs.elements.field');

        return collect($layout->fields());
    }

    // -------------------------------------------------------------------------
    // Querying
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

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

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
}
