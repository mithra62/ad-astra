<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Category\DeleteCategoryRequest;
use App\Http\Requests\Category\EditCategoryRequest;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Models\Category\Group as CategoryGroup;
use Illuminate\Http\Request;

class Category extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        echo __FILE__ . ': '. __LINE__;
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

        return $this->view('categories.create', ['group' => $group]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        $creator = app(CreateNewCategory::class);
        $group = $creator->create($request->all());
        return redirect()->route('categories.groups.show', $group->id)->with('status', trans('category.group.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        echo __FILE__ . ': '. __LINE__;
        exit;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        echo __FILE__ . ': '. __LINE__;
        exit;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditCategoryRequest $request, string $id)
    {
        echo __FILE__ . ': '. __LINE__;
        exit;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        echo __FILE__ . ': '. __LINE__;
        exit;
    }
}
