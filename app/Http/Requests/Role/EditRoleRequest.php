<?php

namespace App\Http\Requests\Role;

use App\Rules\UniqueRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

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
            'name' => [
                'required',
                'string',
                'max:255',
                UniqueRecord::class,
            ], // 'required|string|max:255|unique:roles,name,' . (int)self::segment(2),
            'permissions' => 'required|array'
        ];
    }
}
