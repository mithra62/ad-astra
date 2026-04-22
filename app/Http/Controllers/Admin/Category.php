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

    public function create(string $group_id)
    {
        $group = CategoryGroup::with([
            'fieldLayout.tabs.elements.field.fieldType',
        ])->find($group_id);

        if (! $group instanceof CategoryGroup) {
            abort(404);
        }

        $groups = CategoryGroup::ordered()->get();

        return $this->view('categories.create', [
            'group'  => $group,
            'groups' => $groups,
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $creator  = app(CreateNewCategory::class);
        $category = $creator->create(array_merge($request->all(), [
            'group_id' => $request->route('group_id'),
        ]));

        return redirect()
            ->route('categories.groups.show', $category->group_id)
            ->with('status', trans('category.created'));
    }

    public function show(string $id)
    {
        return redirect()->route('categories.edit', $id);
    }

    public function edit(string $id)
    {
        $category = CategoryModel::with([
            'group.fieldLayout.tabs.elements.field.fieldType',
            'fieldValues.field.fieldType',
        ])->find($id);

        if (! $category instanceof CategoryModel) {
            abort(404);
        }

        $groups = CategoryGroup::ordered()->get();

        return $this->view('categories.edit', [
            'category'    => $category,
            'groups'      => $groups,
            'field_values' => $category->fieldArray(),
        ]);
    }

    public function update(EditCategoryRequest $request, string $id)
    {
        $category = CategoryModel::with([
            'group.fieldLayout.tabs.elements.field.fieldType',
        ])->find($id);

        if (! $category instanceof CategoryModel) {
            abort(404);
        }

        $editor   = app(EditCategory::class);
        $category = $editor->edit($category, $request->all());

        return redirect()
            ->route('categories.edit', $category)
            ->with('success', trans('category.updated'));
    }

    public function destroy(DeleteCategoryRequest $request, string $id)
    {
        $category = CategoryModel::find($id);

        if (! $category instanceof CategoryModel) {
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

        if (! $category instanceof CategoryModel) {
            abort(404);
        }

        $groups = CategoryGroup::ordered()->get();

        return $this->view('categories.delete', [
            'category' => $category,
            'groups'   => $groups,
        ]);
    }
}
