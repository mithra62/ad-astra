<?php

namespace AdAstra\Http\Requests\User;

use AdAstra\Enums\UserStatus;
use AdAstra\Http\Requests\FormRequest;
use AdAstra\Models\Role;
use AdAstra\Support\UserFieldLayout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create user');
    }

    public function rules(): array
    {
        $schema = UserFieldLayout::resolve();
        $assignable = $this->assignableRoleNames();

        return array_merge(
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'password_confirmation' => ['required'],
                'status' => ['nullable', 'string', Rule::in(UserStatus::CREATION_ALLOWED)],
                'roles' => ['required', 'array'],
                'roles.*' => ['string', Rule::in($assignable)],
                'fields' => ['nullable', 'array'],
                'is_author' => ['nullable', 'boolean'],
                'author_display_name' => ['nullable', 'string', 'max:255'],
            ],
            $this->schemaFieldRules($schema)
        );
    }

    protected function assignableRoleNames(): array
    {
        $actor = Auth::user();

        return Role::query()
            ->when(!$actor?->hasRole('super admin'), fn($q) => $q->where('name', '!=', 'super admin'))
            ->pluck('name')
            ->all();
    }

    public function messages(): array
    {
        $schema = UserFieldLayout::resolve();

        return array_merge(
            [
                'email.unique' => 'This email is already registered.',
                'roles.required' => 'You must select at least one role.',
            ],
            $this->schemaFieldMessages($schema)
        );
    }

    public function attributes(): array
    {
        $schema = UserFieldLayout::resolve();

        return array_merge(
            [
                'name' => 'full name',
                'email' => 'email address',
            ],
            $this->schemaFieldAttributes($schema)
        );
    }
}
