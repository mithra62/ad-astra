<?php

namespace AdAstra\Http\Requests\User;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class PasswordUserRequest extends FormRequest
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
            'password' => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ];
    }
}
