<?php

namespace AdAstra\Http\Requests\FieldLayout\Tab\Element;

class EditElementRequest extends StoreElementRequest
{
    public function rules(): array
    {
        return [
            'required' => ['nullable', 'boolean'],
            'hidden' => ['nullable', 'boolean'],
            'readonly' => ['nullable', 'boolean'],
            'disabled' => ['nullable', 'boolean'],
            'label' => ['nullable', 'string', 'max:255'],
            'schema_property' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
