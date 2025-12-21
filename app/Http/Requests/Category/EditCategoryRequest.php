<?php
namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditCategoryRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('edit category');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'id' => 'required',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_groups')->ignore($this->data('id')),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_groups')->ignore($this->data('id')),
            ],
        ];
    }
}
