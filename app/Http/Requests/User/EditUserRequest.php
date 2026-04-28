<?php

namespace App\Http\Requests\User;

use App\Models\UserSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditUserRequest extends StoreUserRequest
{

    public function authorize(): bool
    {
        return Auth::user()->can('edit user');
    }

    public function rules(): array
    {
        $schema = UserSchema::resolved();
        $userId = $this->route()->parameter('user') ?? $this->route()->parameter('id');
        return array_merge(
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($userId)],
                'roles' => ['required', 'array'],
                'roles.*' => ['string', 'exists:roles,name'],
                'fields' => ['nullable', 'array'],
            ],
            $this->schemaFieldRules($schema)
        );
    }
}
