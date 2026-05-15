<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\User\StoreUserRequest;
use App\Support\UserFieldLayout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditUserRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        $schema = UserFieldLayout::resolve();

        return array_merge(
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', Rule::unique('users', 'email')->ignore(Auth::user()->id)],
                'fields' => ['nullable', 'array'],
            ],
            $this->schemaFieldRules($schema)
        );
    }
}
