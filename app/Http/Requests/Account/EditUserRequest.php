<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\FormRequest;
use App\Traits\UserSchemaRules as SchemaTrait;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\User\StoreUserRequest;
use Illuminate\Validation\Rule;

class EditUserRequest extends StoreUserRequest
{
    use SchemaTrait;

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
        return array_merge(
            [
                'name'   => ['required', 'string', 'max:255'],
                'email'  => ['required', 'email', Rule::unique('users', 'email')->ignore(Auth::user()->id)],
                'fields' => ['nullable', 'array'],
            ],
            $this->schemaFieldRules()
        );
    }
}
