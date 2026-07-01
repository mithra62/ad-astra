<?php

namespace AdAstra\Http\Requests\FieldLayout\Tab\Element;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class BulkUpdateElementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit field layout');
    }

    public function rules(): array
    {
        return [
            'elements'                   => ['nullable', 'array'],
            'elements.*.element_id'      => ['required', 'integer', 'exists:field_layout_tab_elements,id'],
            'elements.*.sort_order'      => ['nullable', 'integer', 'min:0'],
            'elements.*.required'        => ['nullable', 'boolean'],
            'elements.*.hidden'          => ['nullable', 'boolean'],
            'elements.*.readonly'        => ['nullable', 'boolean'],
            'elements.*.disabled'        => ['nullable', 'boolean'],
            'elements.*.label'           => ['nullable', 'string', 'max:255'],
            'elements.*.schema_property' => ['nullable', 'string', 'max:255'],
            'elements.*.instructions'    => ['nullable', 'string', 'max:255'],
            'new_fields'                 => ['nullable', 'array'],
            'new_fields.*.field_id'      => ['required', 'integer', 'exists:fields,id'],
            'new_fields.*.sort_order'    => ['nullable', 'integer', 'min:0'],
            'removed_elements'           => ['nullable', 'array'],
            'removed_elements.*'         => ['integer', 'exists:field_layout_tab_elements,id'],
        ];
    }
}
