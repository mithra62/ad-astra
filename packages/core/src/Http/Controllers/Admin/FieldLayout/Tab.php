<?php

namespace AdAstra\Http\Controllers\Admin\FieldLayout;

use AdAstra\Actions\FieldLayout\Tab\CreateNewTab;
use AdAstra\Actions\FieldLayout\Tab\DeleteTab;
use AdAstra\Actions\FieldLayout\Tab\EditTab;
use AdAstra\Http\Controllers\Admin\Controller;
use AdAstra\Http\Requests\FieldLayout\Tab\DeleteTabRequest;
use AdAstra\Http\Requests\FieldLayout\Tab\EditTabRequest;
use AdAstra\Http\Requests\FieldLayout\Tab\StoreTabRequest;
use AdAstra\Models\Field;
use AdAstra\Models\FieldLayout as FieldLayoutModel;
use AdAstra\Models\FieldLayout\Tab as TabModel;

class Tab extends Controller
{
    public function store(StoreTabRequest $request, string $layout_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        if (!$layout instanceof FieldLayoutModel) {
            abort(404);
        }

        app(CreateNewTab::class)->create($layout, $request->validated());

        return redirect()
            ->route('field-layouts.edit', $layout->id)
            ->with('success', trans('field_layout.tab.created'));
    }

    public function create(string $layout_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        if (!$layout instanceof FieldLayoutModel) {
            abort(404);
        }

        return $this->view('field-layouts.tabs.create', array_merge(
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

    public function update(EditTabRequest $request, string $layout_id, string $tab_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        $tab = TabModel::find($tab_id);

        if (!$layout instanceof FieldLayoutModel || !$tab instanceof TabModel || $tab->field_layout_id != $layout->id) {
            abort(404);
        }

        app(EditTab::class)->edit($tab, $request->validated());

        return redirect()
            ->route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id])
            ->with('success', trans('field_layout.tab.updated'));
    }

    public function edit(string $layout_id, string $tab_id)
    {
        $layout = FieldLayoutModel::with('tabs.elements', 'fieldGroups.fields')->find($layout_id);
        $tab = TabModel::with(['elements.field.fieldType'])->find($tab_id);

        if (!$layout instanceof FieldLayoutModel || !$tab instanceof TabModel || $tab->field_layout_id != $layout->id) {
            abort(404);
        }

        // Exclude any field already assigned to ANY tab in this layout, not just the current tab.
        $assignedIds = $layout->tabs->flatMap(fn ($t) => $t->elements->pluck('field_id'))->unique()->all();
        $availableFields = $layout->availableFields()->whereNotIn('id', $assignedIds)->values();
        return $this->view('field-layouts.tabs.edit', array_merge(
            $this->sidebarData(),
            [
                'layout' => $layout,
                'tab' => $tab,
                'available_fields' => $availableFields,
            ]
        ));
    }

    public function fields(string $layout_id, string $tab_id)
    {
        $layout = FieldLayoutModel::with('tabs.elements', 'fieldGroups.fields')->find($layout_id);
        $tab = TabModel::with(['elements.field.fieldType'])->find($tab_id);

        if (!$layout instanceof FieldLayoutModel || !$tab instanceof TabModel || $tab->field_layout_id != $layout->id) {
            abort(404);
        }

        // Exclude any field already assigned to ANY tab in this layout, not just the current tab.
        $assignedIds = $layout->tabs->flatMap(fn ($t) => $t->elements->pluck('field_id'))->unique()->all();
        $paletteIds = $layout->availableFields()->pluck('id')->all();
        $availableFields = Field::with('fieldType')
            ->whereIn('id', $paletteIds)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();

        return $this->view('field-layouts.tabs.fields', array_merge(
            $this->sidebarData(),
            [
                'layout' => $layout,
                'tab' => $tab,
                'available_fields' => $availableFields,
            ]
        ));
    }

    public function confirm(string $layout_id, string $tab_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        $tab = TabModel::withCount('elements')->find($tab_id);

        if (!$layout instanceof FieldLayoutModel || !$tab instanceof TabModel || $tab->field_layout_id != $layout->id) {
            abort(404);
        }

        return $this->view('field-layouts.tabs.delete', array_merge(
            $this->sidebarData(),
            [
                'layout' => $layout,
                'tab' => $tab,
            ]
        ));
    }

    public function destroy(DeleteTabRequest $request, string $layout_id, string $tab_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        $tab = TabModel::find($tab_id);

        if (!$layout instanceof FieldLayoutModel || !$tab instanceof TabModel || $tab->field_layout_id != $layout->id) {
            abort(404);
        }

        app(DeleteTab::class)->delete($tab);

        return redirect()
            ->route('field-layouts.edit', $layout->id)
            ->with('success', trans('field_layout.tab.deleted'));
    }
}
