<?php

namespace App\Http\Controllers\Admin\FieldLayout;

use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\FieldLayout\Tab\Element\DeleteElementRequest;
use App\Http\Requests\FieldLayout\Tab\Element\StoreElementRequest;
use App\Models\FieldLayout as FieldLayoutModel;
use App\Models\FieldLayout\Tab as TabModel;
use App\Models\FieldLayout\TabElement as TabElementModel;

class TabElement extends Controller
{
    public function store(StoreElementRequest $request, string $layout_id, string $tab_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        $tab    = TabModel::find($tab_id);

        if (! $layout instanceof FieldLayoutModel || ! $tab instanceof TabModel || $tab->field_layout_id != $layout->id) {
            abort(404);
        }

        $fieldId = (int) $request->input('field_id');

        if ($tab->elements()->where('field_id', $fieldId)->exists()) {
            return redirect()
                ->route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id])
                ->withErrors(['field_id' => 'That field is already in this tab.']);
        }

        $nextSort = $tab->elements()->max('sort_order') + 1;

        $tab->elements()->create([
            'field_id'   => $fieldId,
            'required'   => $request->boolean('required'),
            'sort_order' => $request->input('sort_order', $nextSort),
        ]);

        return redirect()
            ->route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id])
            ->with('success', trans('field_layout.element.added'));
    }

    public function update(\Illuminate\Http\Request $request, string $layout_id, string $tab_id, string $element_id)
    {
        $layout  = FieldLayoutModel::find($layout_id);
        $tab     = TabModel::find($tab_id);
        $element = TabElementModel::find($element_id);

        if (! $layout instanceof FieldLayoutModel
            || ! $tab instanceof TabModel
            || ! $element instanceof TabElementModel
            || $tab->field_layout_id != $layout->id
            || $element->field_layout_tab_id != $tab->id
        ) {
            abort(404);
        }

        $element->update([
            'required'   => $request->boolean('required'),
            'sort_order' => $request->input('sort_order', $element->sort_order),
        ]);

        return redirect()
            ->route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id])
            ->with('success', trans('field_layout.element.updated'));
    }

    public function confirm(string $layout_id, string $tab_id, string $element_id)
    {
        $layout  = FieldLayoutModel::find($layout_id);
        $tab     = TabModel::find($tab_id);
        $element = TabElementModel::with('field.fieldType')->find($element_id);

        if (! $layout instanceof FieldLayoutModel
            || ! $tab instanceof TabModel
            || ! $element instanceof TabElementModel
            || $tab->field_layout_id != $layout->id
            || $element->field_layout_tab_id != $tab->id
        ) {
            abort(404);
        }

        return $this->view('field-layouts.tabs.elements.delete', [
            'layout'  => $layout,
            'tab'     => $tab,
            'element' => $element,
        ]);
    }

    public function destroy(DeleteElementRequest $request, string $layout_id, string $tab_id, string $element_id)
    {
        $layout  = FieldLayoutModel::find($layout_id);
        $tab     = TabModel::find($tab_id);
        $element = TabElementModel::find($element_id);

        if (! $layout instanceof FieldLayoutModel
            || ! $tab instanceof TabModel
            || ! $element instanceof TabElementModel
            || $tab->field_layout_id != $layout->id
            || $element->field_layout_tab_id != $tab->id
        ) {
            abort(404);
        }

        $element->delete();

        return redirect()
            ->route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id])
            ->with('success', trans('field_layout.element.removed'));
    }
}
