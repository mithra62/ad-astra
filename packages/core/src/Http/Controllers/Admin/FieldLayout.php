<?php

namespace AdAstra\Http\Controllers\Admin;

use AdAstra\Actions\FieldLayout\CreateNewFieldLayout;
use AdAstra\Actions\FieldLayout\DeleteFieldLayout;
use AdAstra\Actions\FieldLayout\EditFieldLayout;
use AdAstra\Http\Requests\FieldLayout\DeleteFieldLayoutRequest;
use AdAstra\Http\Requests\FieldLayout\EditFieldLayoutRequest;
use AdAstra\Http\Requests\FieldLayout\StoreFieldLayoutRequest;
use AdAstra\Models\Field\Group as FieldGroup;
use AdAstra\Models\FieldLayout as FieldLayoutModel;

class FieldLayout extends Controller
{
    public function index()
    {
        $layouts = FieldLayoutModel::withCount(['tabs'])
            ->orderBy('name')
            ->paginate(20);

        return $this->view('field-layouts.index', ['layouts' => $layouts]);
    }

    public function store(StoreFieldLayoutRequest $request)
    {
        $creator = app(CreateNewFieldLayout::class);
        $layout = $creator->create($request->validated());

        return redirect()
            ->route('field-layouts.edit', $layout->id)
            ->with('success', trans('field_layout.created'));
    }

    public function create()
    {
        return $this->view('field-layouts.create', array_merge(
            $this->sidebarData(),
            ['field_groups' => FieldGroup::orderBy('name')->get()]
        ));
    }

    private function sidebarData(): array
    {
        return [
            'layouts' => FieldLayoutModel::orderBy('name')->get(),
        ];
    }

    public function update(EditFieldLayoutRequest $request, string $id)
    {
        $layout = FieldLayoutModel::find($id);
        if (!$layout instanceof FieldLayoutModel) {
            abort(404);
        }

        $editor = app(EditFieldLayout::class);
        $editor->edit($layout, $request->validated());

        return redirect()
            ->route('field-layouts.edit', $id)
            ->with('success', trans('field_layout.updated'));
    }

    public function edit(string $id)
    {
        $layout = FieldLayoutModel::with([
            'tabs.elements.field.fieldType',
            'entryGroups',
            'entryTypes.entryGroup',
            'fieldGroups',
        ])->find($id);

        if (!$layout instanceof FieldLayoutModel) {
            abort(404);
        }

        return $this->view('field-layouts.edit', array_merge(
            $this->sidebarData(),
            [
                'layout' => $layout,
                'field_groups' => FieldGroup::orderBy('name')->get(),
            ]
        ));
    }

    public function confirm(string $id)
    {
        $layout = FieldLayoutModel::withCount('tabs')->find($id);
        if (!$layout instanceof FieldLayoutModel) {
            return redirect()->route('field-layouts')->with('failure', trans('field_layout.not_found'));
        }

        return $this->view('field-layouts.delete', array_merge(
            $this->sidebarData(),
            ['layout' => $layout]
        ));
    }

    public function destroy(DeleteFieldLayoutRequest $request, string $id)
    {
        $layout = FieldLayoutModel::find($id);
        if ($layout instanceof FieldLayoutModel) {
            app(DeleteFieldLayout::class)->delete($layout);

            return redirect()
                ->route('field-layouts')
                ->with('success', trans('field_layout.deleted'));
        }

        return redirect()
            ->route('field-layouts')
            ->with('failure', trans('field_layout.not_found'));
    }
}
