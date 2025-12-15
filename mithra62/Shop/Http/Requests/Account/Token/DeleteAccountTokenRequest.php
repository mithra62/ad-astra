<?php
namespace mithra62\Shop\Http\Requests\Account\Token;

use Illuminate\Foundation\Http\FormRequest;

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
