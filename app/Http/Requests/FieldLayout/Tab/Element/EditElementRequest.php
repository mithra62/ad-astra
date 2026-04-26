<?php

namespace App\Http\Requests\FieldLayout\Tab\Element;

class EditElementRequest extends StoreElementRequest
{
    public function rules(): array
    {
        return [
            'required'   => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
