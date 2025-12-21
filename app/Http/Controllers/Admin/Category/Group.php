<?php

namespace App\Http\Controllers\Admin\Category;

use App\Actions\Category\Group\CreateNewCategoryGroup;
use App\Actions\Category\Group\EditCategoryGroup;
use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\Category\Group\DeleteCategoryGroupRequest;
use App\Http\Requests\Category\Group\EditCategoryRequest;
use App\Http\Requests\Category\Group\StoreCategoryRequest;
use App\Models\Category\Group as CategoryGroup;

class Group extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $groups = CategoryGroup::with('categories')->paginate(20);
        return $this->view('categories.groups.index', ['groups' => $groups]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return $this->view('categories.groups.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        $creator = app(CreateNewCategoryGroup::class);
        $group = $creator->create($request->all());
        return redirect()->route('categories.groups.show', $group->id)->with('status', trans('category.group.created'));
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

        $groups = CategoryGroup::with('categories')->get();
        return $this->view('categories.groups.view', ['group' => $group, 'groups' => $groups]);
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

        return $this->view('categories.groups.edit', ['group' => $group]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditCategoryRequest $request, string $id)
    {
        $group = CategoryGroup::find($id);
        if ($group instanceof CategoryGroup) {
            $editor = app(EditCategoryGroup::class);
            $editor->edit($group, $request->all());
            return redirect()->route('categories.groups')->with('success', trans('category.group.updated'));
        }

        abort(404);
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

        return $this->view('categories.groups.delete', ['group' => $group]);
    }
}
