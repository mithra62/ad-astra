<?php

namespace AdAstra\Http\Requests\FieldLayout\Tab;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTabRequest extends FormRequest
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
                Rule::unique('field_layout_tabs', 'handle'),
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0'
            ],
        ];
    }
}
