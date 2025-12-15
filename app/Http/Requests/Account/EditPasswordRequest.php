<?php

namespace App\Http\Requests\Account;

use App\Rules\Rules\MatchCurrentPassword;
use Illuminate\Foundation\Http\FormRequest;

class EditPasswordRequest extends FormRequest
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
            'password' => 'required|min:8|confirmed',
            'current_password' => ['required', new MatchCurrentPassword],
            'password_confirmation' => 'required',
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array

    {
        return [
            'current_password.required' => 'You must enter your current password.',
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
