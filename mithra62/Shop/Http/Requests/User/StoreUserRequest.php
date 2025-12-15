<?php

namespace mithra62\Shop\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreUserRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('create user');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
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
