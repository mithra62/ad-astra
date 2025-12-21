<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('create category');
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
                Rule::unique('categories')->ignore($this->data('id')),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')->ignore($this->data('id')),
            ],
        ];
    }
}
