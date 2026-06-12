<?php

namespace App\Http\Requests\Category\Group;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditCategoryGroupRequest extends StoreCategoryGroupRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit category group');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'field_layout_id' => [
                'nullable',
                'integer',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_groups')->ignore($this->route()->parameter('group')),
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
                Rule::unique('category_groups', 'handle')->ignore($this->route()->parameter('group')),
            ],
        ];
    }
}
