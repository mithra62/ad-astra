<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Field\CreateNewField;
use App\Actions\Field\EditField;
use App\Http\Requests\Field\DeleteFieldRequest;
use App\Http\Requests\Field\EditFieldRequest;
use App\Http\Requests\Field\StoreFieldRequest;
use App\Models\Field as FieldModel;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\FieldLayout as FieldLayoutModel;
use Illuminate\Http\Request;

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

        $groups      = FieldGroup::all();
        $typeId      = old('field_type_id');
        $defaultType = $typeId
            ? FieldType::find($typeId)
            : FieldType::where('object', \App\Field\Types\Text::class)->first();

        $data = [
            'group'                => $group,
            'groups'               => $groups,
            'field_types'          => app('fields-service')->getFieldOptions(),
            'initial_settings_form' => $defaultType ? $defaultType->instance()->settingsForm() : [],
            'current_values'       => old('settings', []),
        ];

        return $this->view('fields.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFieldRequest $request)
    {
        $creator = app(CreateNewField::class);
        $data = $request->validated();
        $data['group_id'] = $request->group_id;
        $group = $creator->createByGroup($data);
        return redirect()->route('fields.show', $group->id)->with('success', trans('field.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $field = FieldModel::with('groups')->find($id);
        if (!$field instanceof FieldModel) {
            abort(404);
        }

        $groups = FieldGroup::all();
        $active_group = $field->groups->first();
        $layouts = FieldLayoutModel::with(['entryGroups', 'entryTypes.entryGroup'])
            ->whereHas('tabs.elements', fn($q) => $q->where('field_id', $field->id))
            ->orderBy('name')
            ->get();

        $data = [
            'field' => $field,
            'groups' => $groups,
            'active_group' => $active_group,
            'layouts' => $layouts,
        ];

        return $this->view('fields.show', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditFieldRequest $request, string $id)
    {
        $field = FieldModel::find($id);
        if ($field instanceof FieldModel) {
            $editor = app(EditField::class);
            $editor->edit($field, $request->validated());
            return redirect()->route('fields.show', $id)->with('success', trans('field.updated'));
        }

        abort(404);
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

        $groups       = FieldGroup::all();
        $active_group = $field->groups->first();
        $data = [
            'field'                 => $field,
            'groups'                => $groups,
            'field_types'           => app('fields-service')->getFieldOptions(),
            'active_group'          => $active_group,
            'current_type_handle'   => $field->fieldType?->instance()->handle(),
            'initial_settings_form' => $field->fieldType?->instance()->settingsForm() ?? [],
            'current_values'        => old('settings', $field->settings ?? []),
        ];

        return $this->view('fields.edit', $data);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteFieldRequest $request, string $id)
    {
        $field = FieldModel::with('groups')->find($id);
        if ($field instanceof FieldModel) {
            $group = $field->groups()->first();
            $field->delete();
            return redirect()->route('fields.groups.show', $group)->with('success', trans('field.deleted'));
        }

        abort(404);
    }

    /**
     * Returns an HTML fragment with the settings panel for a given field type.
     * Called via AJAX when the type dropdown changes on the create/edit form.
     */
    public function typeSettings(Request $request): \Illuminate\Http\Response
    {
        $request->validate([
            'type_id'  => 'required|integer',
            'field_id' => 'nullable|integer',
        ]);

        $type = FieldType::find($request->type_id);
        if (!$type instanceof FieldType) {
            abort(404);
        }

        $instance = $type->instance();
        $form     = $instance->settingsForm();

        // Merge DB-sourced option lists into each widget descriptor that needs them
        foreach ($instance->settingsFormOptions() as $handle => $optionList) {
            if (isset($form[$handle])) {
                $form[$handle]['options'] = $optionList;
            }
        }

        // Resolve current values from saved field settings (edit) or flashed old() input
        $currentValues = [];
        if ($request->field_id) {
            $field         = FieldModel::find($request->field_id);
            $currentValues = $field?->settings ?? [];
        }
        $currentValues = old('settings', $currentValues);

        // For Slider's 'default' slider-widget: inject the sibling min/max/step/suffix
        // so the slider widget knows its own bounds when rendered
        if (isset($form['default']) && ($form['default']['type'] ?? '') === 'slider') {
            $defaults                   = $instance->settingsDefaults();
            $form['default']['min']     = $currentValues['min']    ?? $defaults['min']    ?? 0;
            $form['default']['max']     = $currentValues['max']    ?? $defaults['max']    ?? 100;
            $form['default']['step']    = $currentValues['step']   ?? $defaults['step']   ?? 1;
            $form['default']['suffix']  = $currentValues['suffix'] ?? $defaults['suffix'] ?? '';
        }

        return response()->view('admin.fields._settings_panel', [
            'settings_form'  => $form,
            'current_values' => $currentValues,
        ]);
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
