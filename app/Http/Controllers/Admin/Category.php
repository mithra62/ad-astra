<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Category\CreateNewCategory;
use App\Actions\Category\EditCategory;
use App\Http\Requests\Category\DeleteCategoryRequest;
use App\Http\Requests\Category\EditCategoryRequest;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Models\Category as CategoryModel;
use App\Models\Category\Group as CategoryGroup;

class Category extends Controller
{
    public function index()
    {
        return redirect()->route('categories.groups');
    }

    public function store(StoreCategoryRequest $request)
    {
        $validated = $request->validated();
        $creator = app(CreateNewCategory::class);
        $category = $creator->create(array_merge($validated, [
            'group_id' => $request->route('group_id'),
        ]));

        $parentId = $validated['parent_id'] ?? null;
        if ($parentId) {
            return redirect()
                ->route('categories.edit', $parentId)
                ->with('status', trans('category.created'));
        }

        return redirect()
            ->route('categories.groups.show', $category->group_id)
            ->with('status', trans('category.created'));
    }

    public function create(string $group_id)
    {
        $group = CategoryGroup::with([
            'fieldLayout.tabs.elements.field.fieldType',
        ])->find($group_id);

        if (!$group instanceof CategoryGroup) {
            abort(404);
        }

        $groups = CategoryGroup::ordered()->get();
        $selectedParentId = (int)request()->query('parent_id') ?: null;

        return $this->view('categories.create', [
            'group' => $group,
            'groups' => $groups,
            'parent_categories' => $this->buildCategoryTree($group->id),
            'selected_parent_id' => $selectedParentId,
        ]);
    }

    private function buildCategoryTree(int $groupId, ?CategoryModel $exclude = null): array
    {
        $all = CategoryModel::inGroup($groupId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'sort_order']);

        $childrenMap = [];
        foreach ($all as $cat) {
            $childrenMap[$cat->parent_id ?? 0][] = $cat;
        }

        $excludeIds = $exclude ? $this->collectDescendantIds($exclude->id, $childrenMap) : [];

        $flat = [];
        foreach ($childrenMap[0] ?? [] as $root) {
            $this->flattenFromMap($root, 0, $flat, $excludeIds, $childrenMap);
        }

        return $flat;
    }

    private function collectDescendantIds(int $catId, array $childrenMap): array
    {
        $ids = [$catId];
        foreach ($childrenMap[$catId] ?? [] as $child) {
            $ids = array_merge($ids, $this->collectDescendantIds($child->id, $childrenMap));
        }
        return $ids;
    }

    private function flattenFromMap(CategoryModel $cat, int $depth, array &$flat, array $excludeIds, array $childrenMap): void
    {
        if (in_array($cat->id, $excludeIds)) {
            return;
        }

        $flat[] = [
            'id' => $cat->id,
            'label' => str_repeat('— ', $depth) . $cat->name,
            'depth' => $depth,
        ];

        foreach ($childrenMap[$cat->id] ?? [] as $child) {
            $this->flattenFromMap($child, $depth + 1, $flat, $excludeIds, $childrenMap);
        }
    }

    public function show(string $id)
    {
        return redirect()->route('categories.edit', $id);
    }

    public function update(EditCategoryRequest $request, string $id)
    {
        $category = CategoryModel::with([
            'group.fieldLayout.tabs.elements.field.fieldType',
        ])->find($id);

        if (!$category instanceof CategoryModel) {
            abort(404);
        }

        $editor = app(EditCategory::class);
        $category = $editor->edit($category, $request->validated());

        return redirect()
            ->route('categories.edit', $category)
            ->with('success', trans('category.updated'));
    }

    public function edit(string $id)
    {
        $category = CategoryModel::with([
            'group.fieldLayout.tabs.elements.field.fieldType',
            'fieldValues.field.fieldType',
            'children.children',
        ])->find($id);

        if (!$category instanceof CategoryModel) {
            abort(404);
        }

        $groups = CategoryGroup::ordered()->get();

        return $this->view('categories.edit', [
            'category' => $category,
            'groups' => $groups,
            'field_values' => $category->fieldArray(),
            'parent_categories' => $this->buildCategoryTree($category->group_id, $category),
        ]);
    }

    public function destroy(DeleteCategoryRequest $request, string $id)
    {
        $category = CategoryModel::find($id);

        if (!$category instanceof CategoryModel) {
            abort(404);
        }

        $groupId = $category->group_id;
        $category->delete();

        return redirect()
            ->route('categories.groups.show', $groupId)
            ->with('success', trans('category.deleted'));
    }

    public function confirm(string $id)
    {
        $category = CategoryModel::with('group')->find($id);

        if (!$category instanceof CategoryModel) {
            abort(404);
        }

        $groups = CategoryGroup::ordered()->get();

        return $this->view('categories.delete', [
            'category' => $category,
            'groups' => $groups,
        ]);
    }
}
