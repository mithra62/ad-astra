<?php

namespace AdAstra\Http\Requests\Role;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreRoleRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('create role');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:roles,name,' . (int)self::segment(2),
            'permissions' => 'required|array',
        ];
    }
}
