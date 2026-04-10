<?php

namespace App\Http\Controllers\Admin\Field;

use App\Actions\Field\Group\CreateNewFieldGroup;
use App\Actions\Field\Group\EditFieldGroup;
use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\Field\Group\DeleteFieldGroupRequest;
use App\Http\Requests\Field\Group\EditFieldGroupRequest;
use App\Http\Requests\Field\Group\StoreFieldGroupRequest;
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;

class Group extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $groups = FieldGroup::with('fields')->paginate(20);
        return $this->view('fields.groups.index', ['groups' => $groups]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return $this->view('fields.groups.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFieldGroupRequest $request)
    {
        $creator = app(CreateNewFieldGroup::class);
        $group = $creator->create($request->all());
        return redirect()->route('fields.groups.show', $group->id)->with('status', trans('field.group.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $group = FieldGroup::find($id);
        if (!$group instanceof FieldGroup) {
            abort(404);
        }

        $groups = FieldGroup::all();
        $fields = $group->fields()->get();
        $data = [
            'group' => $group,
            'groups' => $groups,
            'fields' => $fields,
        ];

        return $this->view('fields.groups.view', $data);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $group = FieldGroup::find($id);
        if (!$group instanceof FieldGroup) {
            abort(404);
        }

        return $this->view('fields.groups.edit', ['group' => $group]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditFieldGroupRequest $request, string $id)
    {
        $group = FieldGroup::find($id);
        if ($group instanceof FieldGroup) {
            $editor = app(EditFieldGroup::class);
            $editor->edit($group, $request->all());
            return redirect()->route('fields.groups')->with('success', trans('field.group.updated'));
        }

        abort(404);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteFieldGroupRequest $request, string $id)
    {
        echo 'fdsa';
        exit;
        $group = FieldGroup::find($id);
        if ($group instanceof FieldGroup) {
            $group->delete();
            return redirect()->route('fields.groups')->with('success', trans('field.group.deleted'));
        }

        return redirect()->route('fields.groups')->with('failure', trans('field.group.not_found'));
    }

    public function confirm(string $id)
    {
        $group = FieldGroup::find($id);
        if (!$group instanceof FieldGroup) {
            return redirect()->route('fields.groups')->with('failure', 'field.group.not_found');
        }

        return $this->view('fields.groups.delete', ['group' => $group]);
    }
}
