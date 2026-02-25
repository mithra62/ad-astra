<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Field\CreateNewField;
use App\Actions\Field\EditField;
use App\Http\Requests\Field\DeleteFieldRequest;
use App\Http\Requests\Field\EditFieldRequest;
use App\Http\Requests\Field\StoreFieldRequest;
use App\Models\Category as FieldModel;
use App\Models\Field\Group as FieldGroup;

class Field extends Controller
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
        $group = FieldGroup::find($group_id);
        if (!$group instanceof FieldGroup) {
            abort(404);
        }

        return $this->view('fields.create', ['group' => $group]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        $creator = app(CreateNewCategory::class);
        $data = $request->all();
        $data['group_id'] = $request->group_id;
        $group = $creator->create($data);
        return redirect()->route('categories.show', $group->id)->with('status', trans('category.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = CategoryModel::with(['group', 'children'])->find($id);
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
        $category = CategoryModel::find($id);
        if (!$category instanceof CategoryModel) {
            abort(404);
        }

        $groups = CategoryGroup::with('categories')->get();
        return $this->view('categories.edit', ['category' => $category, 'groups' => $groups]);
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

        $groups = CategoryGroup::with('categories')->get();
        return $this->view('categories.delete', ['category' => $category]);
    }
}
