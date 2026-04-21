<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Field\CreateNewField;
use App\Actions\Field\EditField;
use App\Http\Requests\Field\DeleteFieldRequest;
use App\Http\Requests\Field\EditFieldRequest;
use App\Http\Requests\Field\StoreFieldRequest;
use App\Models\Field as FieldModel;
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

        $groups = FieldGroup::all();
        $data = [
            'group' => $group,
            'groups' => $groups,
            'field_types' => app('fields-service')->getFieldOptions()
        ];

        return $this->view('fields.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFieldRequest $request)
    {
        $creator = app(CreateNewField::class);
        $data = $request->all();
        $data['group_id'] = $request->group_id;
        $group = $creator->createByGroup($data);
        return redirect()->route('fields.show', $group->id)->with('status', trans('field.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $field = FieldModel::find($id);
        if (!$field instanceof FieldModel) {
            abort(404);
        }

        //$category->with('group');
        print_r($field);
        exit;
        echo __FILE__ . ': ' . __LINE__;
        exit;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $field = FieldModel::find($id);
        if (!$field instanceof FieldModel) {
            abort(404);
        }

        $groups = FieldGroup::all();
        $active_group = $field->groups->first();
        $data = [
            'field' => $field,
            'groups' => $groups,
            'field_types' => app('fields-service')->getFieldOptions(),
            'active_group' => $active_group
        ];

        return $this->view('fields.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditFieldRequest $request, string $id)
    {
        $field = FieldModel::find($id);
        if ($field instanceof FieldModel) {
            $editor = app(EditField::class);
            $editor->edit($field, $request->all());
            return redirect()->route('fields.show', $id)->with('success', trans('field.updated'));
        }

        abort(404);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteFieldRequest $request, string $id)
    {
        $field = FieldModel::find($id);
        if ($field instanceof FieldModel) {
            $field->delete();
            return redirect()->route('fields.groups.show', $id)->with('success', trans('field.deleted'));
        }

        abort(404);
    }

    public function confirm(string $id)
    {
        $field = FieldModel::find($id);
        if (!$field instanceof FieldModel) {
            abort(404);
        }

        $groups = FieldGroup::with('fields')->get();
        $active_group = $field->groups->first();
        $data = [
            'field' => $field,
            'active_group' => $active_group,
            'groups' => $groups,
        ];
        return $this->view('fields.delete', $data);
    }
}
