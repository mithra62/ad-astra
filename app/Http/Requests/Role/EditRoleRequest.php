<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditRoleRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('edit role');
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
                Rule::unique('roles')->ignore($this->data('id')),
            ],
            'permissions' => 'required|array'
        ];
    }
}
