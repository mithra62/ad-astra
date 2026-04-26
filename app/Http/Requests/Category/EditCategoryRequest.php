<?php

namespace App\Http\Requests\Category;

use App\Http\Requests\FormRequest;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Category as Category;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit category');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        $category = Category::find($this->route()->parameter('category'));
        $schema = CategoryGroup::resolvedFields($category->group_id);
        return array_merge(
            [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('categories')->ignore($this->route()->parameter('category')),
                ],
                'handle' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('categories', 'handle')->ignore($this->route()->parameter('category')),
                ],
                'fields' => ['nullable', 'array'],
            ],
            $this->schemaFieldRules($schema)
        );
    }


    public function messages(): array
    {
        $category = Category::find($this->route()->parameter('category'));
        $schema = CategoryGroup::resolvedFields($category->group_id);
        return $this->schemaFieldMessages($schema);
    }

    /**
     * @return array
     */
    public function attributes(): array
    {
        $category = Category::find($this->route()->parameter('category'));
        $schema = CategoryGroup::resolvedFields($category->group_id);
        return $this->schemaFieldAttributes($schema);
    }
}
