<?php

namespace App\Http\Requests\Field\Group;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditFieldGroupRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('edit field group');
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
                Rule::unique('field_groups')->ignore($this->route()->parameter('group')),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('field_groups')->ignore($this->route()->parameter('group')),
            ],
        ];
    }
}
