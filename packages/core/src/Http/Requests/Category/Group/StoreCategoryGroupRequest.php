<?php

namespace AdAstra\Http\Requests\Category\Group;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreCategoryGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create category group');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_groups')->ignore($this->data('id')),
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
            'handle' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_groups', 'handle')->ignore($this->data('id')),
            ],
            'field_layout_id' => [
                'nullable',
                'integer',
            ],
        ];
    }
}
