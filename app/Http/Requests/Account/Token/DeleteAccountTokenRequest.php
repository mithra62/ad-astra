<?php
namespace App\Http\Requests\Account\Token;

use App\Http\Requests\FormRequest;

class DeleteAccountTokenRequest extends FormRequest
{
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
        return [
            'confirm_removal' => 'required'
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array

    {
        return [
            'confirm_removal.required' => 'You must confirm removal of the token.',
        ];
    }
}
