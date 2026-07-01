<?php

namespace AdAstra\Http\Requests\Field\Group;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditFieldGroupRequest extends StoreFieldGroupRequest
{
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('field_groups')->ignore($this->route()->parameter('group')),
            ],
            'handle' => [
                'required',
                'string',
                'max:255',
                Rule::unique('field_groups', 'handle')->ignore($this->route()->parameter('group')),
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ];
    }
}
