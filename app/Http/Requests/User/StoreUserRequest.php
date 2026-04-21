<?php

namespace App\Http\Requests\User;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Traits\UserSchemaRules AS SchemaTrait;

class StoreUserRequest extends FormRequest
{
    use SchemaTrait;

    public function authorize(): bool
    {
        return Auth::user()->can('create user');
    }

    public function rules(): array
    {
        return array_merge(
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'password_confirmation' => ['required'],
                'roles' => ['required', 'array'],
                'roles.*' => ['string', 'exists:roles,name'],
                'fields' => ['nullable', 'array'],
            ],
            $this->schemaFieldRules()
        );
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered.',
            'roles.required' => 'You must select at least one role.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
        ];
    }
}
