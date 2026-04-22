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
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        echo __FILE__ . ': ' . __LINE__;
        exit;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($group_id)
    {
        $group = CategoryGroup::find($group_id);
        if (!$group instanceof CategoryGroup) {
            abort(404);
        }

        $groups = CategoryGroup::get();
        $data = [
            'group' => $group,
            'groups' => $groups,
        ];

        return $this->view('categories.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        $creator = app(CreateNewCategory::class);
        $data = $request->all();
        $data['group_id'] = $request->group_id;
        $cat = $creator->createByGroup($data);
        return redirect()->route('categories.show', $cat->id)->with('status', trans('category.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = CategoryModel::with(['children'])->find($id);
        if (!$category instanceof CategoryModel) {
            abort(404);
        }

        //$category->with('group');
        print_r($category);
        exit;
        echo __FILE__ . ': ' . __LINE__;
        exit;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $category = CategoryModel::with('group')->find($id);
        if (!$category instanceof CategoryModel) {
            abort(404);
        }

        $groups = CategoryGroup::get();
        $active_group = $category->group->first();
        $data = [
            'category' => $category,
            'groups' => $groups,
            'active_group' => $active_group
        ];
        return $this->view('categories.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditCategoryRequest $request, string $id)
    {
        $category = CategoryModel::find($id);
        if ($category instanceof CategoryModel) {
            $editor = app(EditCategory::class);
            $editor->edit($category, $request->all());
            return redirect()->route('categories.groups.show', $category->group)->with('success', trans('category.updated'));
        }

        abort(404);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteCategoryRequest $request, string $id)
    {
        $category = CategoryModel::find($id);
        if ($category instanceof CategoryModel) {
            $group = $category->group;
            $category->delete();
            return redirect()->route('categories.groups.show', $group)->with('success', trans('category.deleted'));
        }

        abort(404);
    }

    public function confirm(string $id)
    {
        $category = CategoryModel::find($id);
        if (!$category instanceof CategoryModel) {
            abort(404);
        }

        $groups = CategoryGroup::get();
        $active_group = $category->group->first();
        return $this->view('categories.delete', ['category' => $category, 'groups' => $groups, 'active_group' => $active_group]);
    }
}
