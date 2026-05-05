<?php

namespace App\Http\Requests\Field;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create field');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'field_type_id' => [
                'required',
                'integer',
                'max:255',
            ],
            'label' => [
                'nullable',
                'string',
                'max:255',
            ],
            'instructions' => [
                'nullable',
                'string',
                'max:255',
            ],
            'hidden' => [
                'nullable',
                'boolean',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('fields')->ignore($this->route()->parameter('field')),
            ],
            'handle' => [
                'required',
                'string',
                'max:255',
                Rule::unique('fields', 'handle')->ignore($this->route()->parameter('field')),
            ],
        ];
    }
}
