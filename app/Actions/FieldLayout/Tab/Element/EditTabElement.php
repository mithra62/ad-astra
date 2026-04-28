<?php

namespace App\Actions\FieldLayout\Tab\Element;

use App\Actions\AbstractAction;
use App\Models\FieldLayout\TabElement;

class EditTabElement extends AbstractAction
{
    public function edit(TabElement $element, array $input): TabElement
    {
        $element->update([
            'required' => (bool)($input['required'] ?? false),
            'sort_order' => $input['sort_order'] ?? $element->sort_order,
        ]);

        return $element->fresh();
    }
}
