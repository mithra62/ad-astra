<?php

namespace App\Http\Requests\Category\Group;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditCategoryGroupRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_groups')->ignore($this->route()->parameter('group')),
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
