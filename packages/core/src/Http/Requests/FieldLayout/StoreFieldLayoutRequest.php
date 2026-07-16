<?php

namespace AdAstra\Http\Requests\FieldLayout;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreFieldLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create field layout');
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
                Rule::unique('field_layouts', 'handle'),
            ],
            'field_groups' => ['nullable', 'array'],
            'field_groups.*' => ['integer', 'exists:field_groups,id'],
        ];
    }
}
