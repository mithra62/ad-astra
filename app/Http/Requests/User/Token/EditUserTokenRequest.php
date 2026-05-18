<?php

namespace App\Http\Requests\User\Token;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class EditUserTokenRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('edit user token');
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
