<?php

namespace App\Http\Requests\User;

use App\Http\Requests\FormRequest;
use App\Models\UserSchema;
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
     * @return array
     */
    public function rules(): array
    {
        $schema = UserSchema::resolved();
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
            $this->schemaFieldRules($schema)
        );
    }

    /**
     * @return array
     */
    public function messages(): array
    {
        $schema = UserSchema::resolved();
        return array_merge(
            [
                'email.unique' => 'This email is already registered.',
                'roles.required' => 'You must select at least one role.',
            ],
            $this->schemaFieldMessages($schema)
        );
    }

    /**
     * @return array
     */
    public function attributes(): array
    {
        $schema = UserSchema::resolved();
        return array_merge(
            [
                'name' => 'full name',
                'email' => 'email address',
            ],
            $this->schemaFieldAttributes($schema)
        );
    }
}
