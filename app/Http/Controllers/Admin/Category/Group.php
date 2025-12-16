<?php

namespace App\Http\Controllers\Admin\Category;

use App\Http\Controllers\Admin\Controller;
use Illuminate\Http\Request;
use App\Models\Category\Group AS CategoryGroup;
use App\Http\Requests\Category\Group\StoreCategoryGroupRequest;
use App\Http\Requests\Category\Group\DeleteCategoryGroupRequest;
use App\Http\Requests\Category\Group\EditCategoryGroupRequest;

class Group extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $groups = CategoryGroup::paginate(20);
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
    public function store(StoreCategoryGroupRequest $request)
    {
        echo __FILE__ . ': '. __LINE__;
        exit;
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
    public function update(Request $request, string $id)
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
