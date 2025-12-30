<?php

namespace App\Http\Controllers\Admin\Media;

use App\Http\Controllers\Admin\Controller;
use Illuminate\Http\Request;
use App\Models\Media\Library as LibraryModel;

class Library extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $libraries = LibraryModel::paginate(20);
        return $this->view('media.library.index', ['libraries' => $libraries]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return $this->view('media.library.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
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
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    public function confirm()
    {

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
