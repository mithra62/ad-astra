<?php

namespace AdAstra\Http\Requests\Account;

use AdAstra\Http\Requests\FormRequest;
use AdAstra\Rules\MatchCurrentPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore(Auth::id())],
            'current_password' => ['required', new MatchCurrentPassword()],
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered.',
            'current_password.required' => 'You must enter your current password.',
        ];
    }
}
