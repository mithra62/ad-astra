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
        $fieldId = (int) $input['field_id'];

        if ($tab->elements()->where('field_id', $fieldId)->exists()) {
            throw ValidationException::withMessages(['field_id' => 'That field is already in this tab.']);
        }

        $nextSort = $tab->elements()->max('sort_order') + 1;

        return $tab->elements()->create([
            'field_id' => $fieldId,
            'required' => (bool) ($input['required'] ?? false),
            'sort_order' => $input['sort_order'] ?? $nextSort,
        ]);
    }
}
