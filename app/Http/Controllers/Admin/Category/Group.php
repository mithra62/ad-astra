<?php

namespace App\Http\Controllers\Admin\Category;

use App\Actions\Category\Group\CreateNewCategoryGroup;
use App\Actions\Category\Group\EditCategoryGroup;
use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\Category\Group\DeleteCategoryGroupRequest;
use App\Http\Requests\Category\Group\EditCategoryGroupRequest;
use App\Http\Requests\Category\Group\StoreCategoryGroupRequest;
use App\Models\Category\Group as CategoryGroup;
use App\Models\FieldLayout;

class Group extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $groups = CategoryGroup::with('categories')->paginate($this->total_per_page);
        return $this->view('categories.groups.index', ['groups' => $groups]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryGroupRequest $request)
    {
        $creator = app(CreateNewCategoryGroup::class);
        $group = $creator->create($request->validated());
        return redirect()->route('categories.groups.show', $group->id)->with('success', trans('category.group.created'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = [
            'field_layouts' => FieldLayout::orderBy('name')->get()
        ];
        return $this->view('categories.groups.create', $data);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $group = CategoryGroup::find($id);
        if (!$group instanceof CategoryGroup) {
            abort(404);
        }

        $groups = CategoryGroup::all();
        $categories = $group->categories()->with('children')->whereNull('parent_id')->paginate($this->total_per_page);
        $data = [
            'group' => $group,
            'groups' => $groups,
            'categories' => $categories,
        ];

        return $this->view('categories.groups.view', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditCategoryGroupRequest $request, string $id)
    {
        $group = CategoryGroup::find($id);
        if ($group instanceof CategoryGroup) {
            $editor = app(EditCategoryGroup::class);
            $editor->edit($group, $request->validated());
            return redirect()->route('categories.groups.show', $group)->with('success', trans('category.group.updated'));
        }

        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $group = CategoryGroup::find($id);
        if (!$group instanceof CategoryGroup) {
            abort(404);
        }

        $groups = CategoryGroup::ordered()->get();
        $data = [
            'group' => $group,
            'groups' => $groups,
            'field_layouts' => FieldLayout::orderBy('name')->get()
        ];
        return $this->view('categories.groups.edit', $data);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteCategoryGroupRequest $request, string $id)
    {
        $group = CategoryGroup::find($id);
        if ($group instanceof CategoryGroup) {
            $group->delete();
            return redirect()->route('categories.groups')->with('success', trans('category.group.deleted'));
        }

        return redirect()->route('categories.groups')->with('failure', trans('category.group.not_found'));
    }

    public function confirm(string $id)
    {
        $group = CategoryGroup::find($id);
        if (!$group instanceof CategoryGroup) {
            return redirect()->route('categories.groups')->with('failure', 'category.group.not_found');
        }

        $groups = CategoryGroup::all();
        return $this->view('categories.groups.delete', ['group' => $group, 'groups' => $groups]);
    }
}
