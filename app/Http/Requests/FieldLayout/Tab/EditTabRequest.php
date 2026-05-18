<?php

namespace App\Http\Requests\FieldLayout\Tab;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditTabRequest extends FormRequest
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
                Rule::unique('field_layout_tabs', 'handle')->ignore($this->route()->parameter('id')),
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0'
            ],
        ];
    }
}
