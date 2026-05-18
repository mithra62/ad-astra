<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Admin\Controller;
use App\Models\FieldLayout as FieldLayoutModel;
use App\Support\UserFieldLayout;
use Illuminate\Http\Request;

class Layout extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show()
    {
        $layoutId = UserFieldLayout::resolvedId();
        $layout = $layoutId ? FieldLayoutModel::with([
            'tabs.elements.field.fieldType',
            'entryGroups',
            'entryTypes.entryGroup',
        ])->find($layoutId) : null;

        if (! $layout instanceof FieldLayoutModel) {
            abort(404);
        }

        return $this->view('field-layouts.edit', array_merge(
            $this->sidebarData(),
            ['layout' => $layout]
        ));
    }

    private function sidebarData(): array
    {
        return [
            'layouts' => FieldLayoutModel::orderBy('name')->get(),
        ];
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
