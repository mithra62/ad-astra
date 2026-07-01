<?php

namespace AdAstra\Http\Requests\FieldLayout\Tab\Element;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreElementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit field layout');
    }

    public function rules(): array
    {
        return [
            'field_id' => ['required', 'integer', 'exists:fields,id'],
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
