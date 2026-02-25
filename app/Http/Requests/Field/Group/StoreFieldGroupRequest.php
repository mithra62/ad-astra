<?php

namespace App\Http\Requests\Field\Group;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreFieldGroupRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('create field group');
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
                Rule::unique('field_groups')->ignore($this->data('id')),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('field_groups')->ignore($this->data('id')),
            ],
        ];
    }
}
