<?php

namespace AdAstra\Http\Requests\Category;

use AdAstra\Http\Requests\FormRequest;
use AdAstra\Models\Category as Category;
use AdAstra\Models\Category\Group as CategoryGroup;
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

        // Missing record: skip schema resolution and let the controller return
        // a 404 rather than dereferencing a null category here.
        if (!$category) {
            return ['name' => ['required', 'string', 'max:255']];
        }

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
                'parent_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('categories', 'id')
                        ->where('group_id', $category->group_id)
                        ->whereNot('id', $this->route()->parameter('category')),
                ],
            ],
            $this->schemaFieldRules($schema)
        );
    }


    public function messages(): array
    {
        $category = Category::find($this->route()->parameter('category'));
        if (!$category) {
            return [];
        }

        $schema = CategoryGroup::resolvedFields($category->group_id);
        return $this->schemaFieldMessages($schema);
    }

    /**
     * @return array
     */
    public function attributes(): array
    {
        $category = Category::find($this->route()->parameter('category'));
        if (!$category) {
            return [];
        }

        $schema = CategoryGroup::resolvedFields($category->group_id);
        return $this->schemaFieldAttributes($schema);
    }
}
