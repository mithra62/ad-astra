<?php

namespace mithra62\Shop\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class EditUserRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . Auth::user()->id
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
