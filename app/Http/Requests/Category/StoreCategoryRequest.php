<?php

namespace App\Http\Requests\Category;

use App\Http\Requests\FormRequest;
use App\Models\Category\Group as CategoryGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create category');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        $schema = CategoryGroup::resolvedFields($this->route()->parameter('group_id'));
        return array_merge(
            [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'handle' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'fields' => ['nullable', 'array'],
                'parent_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('categories', 'id')->where('group_id', $this->route()->parameter('group_id')),
                ],
            ],
            $this->schemaFieldRules($schema));
    }

    public function messages(): array
    {
        $schema = CategoryGroup::resolvedFields($this->route()->parameter('group_id'));
        return $this->schemaFieldMessages($schema);
    }

    /**
     * @return array
     */
    public function attributes(): array
    {
        $schema = CategoryGroup::resolvedFields($this->route()->parameter('group_id'));
        return $this->schemaFieldAttributes($schema);
    }
}
