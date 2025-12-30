<?php

namespace App\Http\Controllers\Admin\Media;

use App\Actions\Category\Group\EditCategoryGroup;
use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\Category\Group\DeleteCategoryGroupRequest;
use App\Models\Category\Group as CategoryGroup;
use Illuminate\Http\Request;
use App\Models\Media\Library as LibraryModel;
use App\Http\Requests\Media\Library\StoreMediaLibraryFormRequest;

class Library extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $libraries = LibraryModel::paginate(20);
        return $this->view('media.libraries.index', ['libraries' => $libraries]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $category_groups = CategoryGroup::all();
        return $this->view('media.libraries.create', ['category_groups' => $category_groups]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMediaLibraryFormRequest $request)
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
        $group = LibraryModel::find($id);
        if (!$group instanceof LibraryModel) {
            abort(404);
        }

        $groups = LibraryModel::all();
        $categories = CategoryModel::where(['group_id' => $group->id])->whereNull('parent_id')->get();
        $data = [
            'group' => $group,
            'groups' => $groups,
            'categories' => $categories,
        ];

        return $this->view('categories.groups.view', $data);
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
    public function update(Request $request, string $id)
    {
        $group = CategoryGroup::find($id);
        if ($group instanceof CategoryGroup) {
            $editor = app(EditCategoryGroup::class);
            $editor->edit($group, $request->all());
            return redirect()->route('categories.groups')->with('success', trans('category.group.updated'));
        }

        abort(404);
    }

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
