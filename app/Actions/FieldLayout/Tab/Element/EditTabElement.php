<?php

namespace App\Actions\FieldLayout\Tab\Element;

use App\Actions\AbstractAction;
use App\Models\FieldLayout\TabElement;

class EditTabElement extends AbstractAction
{
    public function edit(TabElement $element, array $input): TabElement
    {
        $element->update([
            'required'        => (bool)($input['required'] ?? false),
            'hidden'          => (bool)($input['hidden'] ?? false),
            'readonly'        => (bool)($input['readonly'] ?? false),
            'disabled'        => (bool)($input['disabled'] ?? false),
            'label'           => $input['label'] ?? $element->label,
            'schema_property' => $input['schema_property'] ?? $element->schema_property,
            'instructions'    => $input['instructions'] ?? $element->instructions,
            'sort_order'      => $input['sort_order'] ?? $element->sort_order,
        ]);

        return $element->fresh();
    }
}
