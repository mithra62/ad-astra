<?php

namespace App\Http\Requests\Category\Group;

use App\Http\Requests\FormRequest;
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
            'field_groups' => [
                'nullable',
                'array',
            ],
            'field_groups.*' => ['integer', 'exists:field_groups,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_groups')->ignore($this->data('id')),
            ],
            'handle' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_groups', 'handle')->ignore($this->data('id')),
            ],
        ];
    }
}
