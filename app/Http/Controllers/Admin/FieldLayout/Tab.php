<?php

namespace App\Http\Controllers\Admin\FieldLayout;

use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\FieldLayout\Tab\DeleteTabRequest;
use App\Http\Requests\FieldLayout\Tab\EditTabRequest;
use App\Http\Requests\FieldLayout\Tab\StoreTabRequest;
use App\Models\FieldLayout as FieldLayoutModel;
use App\Models\FieldLayout\Tab as TabModel;

class Tab extends Controller
{
    public function create(string $layout_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        if (! $layout instanceof FieldLayoutModel) {
            abort(404);
        }

        return $this->view('field-layouts.tabs.create', array_merge(
            $this->sidebarData(),
            ['layout' => $layout]
        ));
    }

    public function store(StoreTabRequest $request, string $layout_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        if (! $layout instanceof FieldLayoutModel) {
            abort(404);
        }

        $layout->tabs()->create([
            'name'       => $request->input('name'),
            'sort_order' => $request->input('sort_order', 0),
        ]);

        return redirect()
            ->route('field-layouts.edit', $layout->id)
            ->with('success', trans('field_layout.tab.created'));
    }

    public function edit(string $layout_id, string $tab_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        $tab    = TabModel::with(['elements.field.fieldType'])->find($tab_id);

        if (! $layout instanceof FieldLayoutModel || ! $tab instanceof TabModel || $tab->field_layout_id != $layout->id) {
            abort(404);
        }

        $availableFields = \App\Models\Field::orderBy('name')->get();

        return $this->view('field-layouts.tabs.edit', array_merge(
            $this->sidebarData(),
            [
                'layout'           => $layout,
                'tab'              => $tab,
                'available_fields' => $availableFields,
            ]
        ));
    }

    public function update(EditTabRequest $request, string $layout_id, string $tab_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        $tab    = TabModel::find($tab_id);

        if (! $layout instanceof FieldLayoutModel || ! $tab instanceof TabModel || $tab->field_layout_id != $layout->id) {
            abort(404);
        }

        $tab->update([
            'name'       => $request->input('name'),
            'sort_order' => $request->input('sort_order', 0),
        ]);

        return redirect()
            ->route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id])
            ->with('success', trans('field_layout.tab.updated'));
    }

    public function confirm(string $layout_id, string $tab_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        $tab    = TabModel::withCount('elements')->find($tab_id);

        if (! $layout instanceof FieldLayoutModel || ! $tab instanceof TabModel || $tab->field_layout_id != $layout->id) {
            abort(404);
        }

        return $this->view('field-layouts.tabs.delete', array_merge(
            $this->sidebarData(),
            [
                'layout' => $layout,
                'tab'    => $tab,
            ]
        ));
    }

    public function destroy(DeleteTabRequest $request, string $layout_id, string $tab_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        $tab    = TabModel::find($tab_id);

        if (! $layout instanceof FieldLayoutModel || ! $tab instanceof TabModel || $tab->field_layout_id != $layout->id) {
            abort(404);
        }

        $tab->delete();

        return redirect()
            ->route('field-layouts.edit', $layout->id)
            ->with('success', trans('field_layout.tab.deleted'));
    }

    private function sidebarData(): array
    {
        return [
            'layouts' => FieldLayoutModel::orderBy('name')->get(),
        ];
    }
}
