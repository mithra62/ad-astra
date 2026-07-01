<?php

namespace AdAstra\Http\Requests\Account\Token;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class EditAccountTokenRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('api');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }
}
