<?php

namespace App\Http\Controllers\Admin\FieldLayout;

use App\Actions\FieldLayout\Tab\Element\CreateTabElement;
use App\Actions\FieldLayout\Tab\Element\DeleteTabElement;
use App\Actions\FieldLayout\Tab\Element\EditTabElement;
use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\FieldLayout\Tab\Element\DeleteElementRequest;
use App\Http\Requests\FieldLayout\Tab\Element\EditElementRequest;
use App\Http\Requests\FieldLayout\Tab\Element\StoreElementRequest;
use App\Models\FieldLayout as FieldLayoutModel;
use App\Models\FieldLayout\Tab as TabModel;
use App\Models\FieldLayout\TabElement as TabElementModel;

class TabElement extends Controller
{
    public function store(StoreElementRequest $request, string $layout_id, string $tab_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        $tab = TabModel::find($tab_id);

        if (!$layout instanceof FieldLayoutModel || !$tab instanceof TabModel || $tab->field_layout_id != $layout->id) {
            abort(404);
        }

        app(CreateTabElement::class)->create($tab, $request->validated());

        return redirect()
            ->route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id])
            ->with('success', trans('field_layout.element.added'));
    }

    public function update(EditElementRequest $request, string $layout_id, string $tab_id, string $element_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        $tab = TabModel::find($tab_id);
        $element = TabElementModel::find($element_id);

        if (!$layout instanceof FieldLayoutModel
            || !$tab instanceof TabModel
            || !$element instanceof TabElementModel
            || $tab->field_layout_id != $layout->id
            || $element->field_layout_tab_id != $tab->id
        ) {
            abort(404);
        }

        app(EditTabElement::class)->edit($element, $request->validated());

        return redirect()
            ->route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id])
            ->with('success', trans('field_layout.element.updated'));
    }

    public function confirm(string $layout_id, string $tab_id, string $element_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        $tab = TabModel::find($tab_id);
        $element = TabElementModel::with('field.fieldType')->find($element_id);

        if (!$layout instanceof FieldLayoutModel
            || !$tab instanceof TabModel
            || !$element instanceof TabElementModel
            || $tab->field_layout_id != $layout->id
            || $element->field_layout_tab_id != $tab->id
        ) {
            abort(404);
        }

        return $this->view('field-layouts.tabs.elements.delete', array_merge(
            $this->sidebarData(),
            [
                'layout' => $layout,
                'tab' => $tab,
                'element' => $element,
            ]
        ));
    }

    private function sidebarData(): array
    {
        return [
            'layouts' => FieldLayoutModel::orderBy('name')->get(),
        ];
    }

    public function destroy(DeleteElementRequest $request, string $layout_id, string $tab_id, string $element_id)
    {
        $layout = FieldLayoutModel::find($layout_id);
        $tab = TabModel::find($tab_id);
        $element = TabElementModel::find($element_id);

        if (!$layout instanceof FieldLayoutModel
            || !$tab instanceof TabModel
            || !$element instanceof TabElementModel
            || $tab->field_layout_id != $layout->id
            || $element->field_layout_tab_id != $tab->id
        ) {
            abort(404);
        }

        app(DeleteTabElement::class)->delete($element);

        return redirect()
            ->route('field-layouts.tabs.edit', ['layout_id' => $layout->id, 'tab_id' => $tab->id])
            ->with('success', trans('field_layout.element.removed'));
    }
}
