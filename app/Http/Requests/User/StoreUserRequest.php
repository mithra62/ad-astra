<?php

namespace App\Http\Requests\User;

use App\Http\Requests\FormRequest;
use App\Traits\UserSchemaRules as SchemaTrait;
use Illuminate\Support\Facades\Auth;

class StoreUserRequest extends FormRequest
{
    use SchemaTrait;

    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('create user');
    }

    /**
     * @return array
     */
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

    /**
     * @return array
     */
    public function messages(): array
    {
        return array_merge(
            [
                'email.unique' => 'This email is already registered.',
                'roles.required' => 'You must select at least one role.',
            ],
            $this->schemaFieldMessages()
        );
    }

    /**
     * @return array
     */
    public function attributes(): array
    {
        return array_merge(
            [
                'name' => 'full name',
                'email' => 'email address',
            ],
            $this->schemaFieldAttributes()
        );
    }
}
