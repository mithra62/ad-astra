<?php

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
use App\Support\UserFieldLayout;
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
        $schema = UserFieldLayout::resolve();
        $userId = $this->route()->parameter('user') ?? $this->route()->parameter('id');
        $assignable = $this->assignableRoleNames();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'roles' => ['required', 'array'],
            'roles.*' => ['string', Rule::in($assignable)],
            'fields' => ['nullable', 'array'],
        ];

        // Only accept a 'status' field from callers who hold the dedicated
        // permission.  Without it, any submitted status value is stripped by
        // UserService::update() anyway, but rejecting it here gives a clear
        // validation error rather than a silent no-op.
        if (Auth::user()->can('manage user status')) {
            $rules['status'] = ['nullable', 'string', Rule::in(UserStatus::ALL)];
        }

        return array_merge($rules, $this->schemaFieldRules($schema));
    }
}
