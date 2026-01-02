<?php

namespace App\Http\Controllers\Admin\Media;

use App\Actions\Category\Group\EditCategoryGroup;
use App\Http\Controllers\Admin\Controller;
use App\Models\Category\Group as CategoryGroup;
use Illuminate\Http\Request;
use App\Models\Media\Library as LibraryModel;
use App\Http\Requests\Media\Library\StoreMediaLibraryFormRequest;
use App\Actions\Media\Library\CreateNewMediaLibrary;

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
        $creator = app(CreateNewMediaLibrary::class);
        $library = $creator->create($request->all());
        return redirect()->route('media.libraries.show', $library->id)->with('status', trans('category.group.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $library = LibraryModel::find($id);
        if (!$library instanceof LibraryModel) {
            abort(404);
        }

        $data = [
            'library' => $library,
        ];

        return $this->view('media.libraries.view', $data);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $library = LibraryModel::find($id);
        if (!$library instanceof LibraryModel) {
            abort(404);
        }

        $category_groups = CategoryGroup::all();
        return $this->view('media.libraries.edit', ['library' => $library, 'category_groups' => $category_groups]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $library = LibraryModel::find($id);
        if ($library instanceof LibraryModel) {
            $editor = app(EditCategoryGroup::class);
            $editor->edit($library, $request->all());
            return redirect()->route('media.libraries')->with('success', trans('media.library.updated'));
        }

        abort(404);
    }

    public function destroy(DeleteCategoryGroupRequest $request, string $id)
    {
        $library = LibraryModel::find($id);
        if ($library instanceof CategoryGroup) {
            $library->delete();
            return redirect()->route('media.libraries')->with('success', trans('media.library.deleted'));
        }

        return redirect()->route('media.libraries')->with('failure', trans('media.library.not_found'));
    }

    public function confirm(string $id)
    {
        $library = LibraryModel::find($id);
        if (!$library instanceof LibraryModel) {
            return redirect()->route('media.libraries')->with('failure', 'media.library.not_found');
        }

        return $this->view('media.libraries.delete', ['group' => $library]);
    }
}
