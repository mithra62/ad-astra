<?php

namespace AdAstra\Http\Requests\Field\Group;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreFieldGroupRequest extends FormRequest
{
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
            'handle' => [
                'required',
                'string',
                'max:255',
                Rule::unique('field_groups', 'handle')->ignore($this->data('id')),
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ];
    }
}
