<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class EditUserRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('edit user');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . (int)request()->post('user_id'),
            'roles' => 'required|array'
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array

    {
        return [
            'terms.accepted' => 'You must accept the Terms of Service and Privacy Policy.',
            'email.unique' => 'This email is already registered. Try logging in instead.',
            'roles.required' => 'You must select at least one role.',
        ];
    }

    /**
     * @return string[]
     */
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
        ];
    }
}
