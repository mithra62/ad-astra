<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\FormRequest;
use App\Rules\MatchCurrentPassword;

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
        ];
    }
}
