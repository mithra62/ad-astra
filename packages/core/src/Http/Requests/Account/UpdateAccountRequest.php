<?php

namespace AdAstra\Http\Requests\Account;

use AdAstra\Http\Requests\FormRequest;
use AdAstra\Support\UserFieldLayout;
use Illuminate\Support\Facades\Auth;

class UpdateAccountRequest extends FormRequest
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
        $schema = UserFieldLayout::resolve();

        return array_merge(
            [
                'name' => ['required', 'string', 'max:255'],
                'fields' => ['nullable', 'array'],
            ],
            $this->schemaFieldRules($schema)
        );
    }
}
