<?php

namespace AdAstra\Http\Requests\FieldLayout;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditFieldLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit field layout');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255'
            ],
            'handle' => [
                'required',
                'string',
                'max:255',
                Rule::unique('field_layouts', 'handle')->ignore($this->route()->parameter('id')),
            ],
            'field_groups' => ['nullable', 'array'],
            'field_groups.*' => ['integer', 'exists:field_groups,id'],
        ];
    }
}
