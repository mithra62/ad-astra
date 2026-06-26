<?php

namespace App\Actions\FieldLayout\Tab\Element;

use App\Actions\AbstractAction;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use Illuminate\Validation\ValidationException;

class CreateTabElement extends AbstractAction
{
    public function create(Tab $tab, array $input): TabElement
    {
        $fieldId = (int)$input['field_id'];

        if (!$tab->layout->availableFields()->pluck('id')->contains($fieldId)) {
            throw ValidationException::withMessages(['field_id' => "That field is not in this layout's field groups."]);
        }

        $layoutTabIds = Tab::where('field_layout_id', $tab->field_layout_id)->pluck('id');
        if (TabElement::whereIn('field_layout_tab_id', $layoutTabIds)->where('field_id', $fieldId)->exists()) {
            throw ValidationException::withMessages(['field_id' => 'That field is already in this layout.']);
        }

        $nextSort = $tab->elements()->max('sort_order') + 1;

        return $tab->elements()->create([
            'field_id'        => $fieldId,
            'required'        => (bool)($input['required'] ?? false),
            'hidden'          => (bool)($input['hidden'] ?? false),
            'readonly'        => (bool)($input['readonly'] ?? false),
            'disabled'        => (bool)($input['disabled'] ?? false),
            'label'           => $input['label'] ?? null,
            'schema_property' => $input['schema_property'] ?? null,
            'instructions'    => $input['instructions'] ?? null,
            'sort_order'      => $input['sort_order'] ?? $nextSort,
        ]);
    }
}
