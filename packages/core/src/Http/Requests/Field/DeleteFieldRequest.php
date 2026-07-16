<?php

namespace AdAstra\Http\Requests\Field;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeleteFieldRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('delete field');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'confirm_removal' => 'required',
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'confirm_removal.required' => 'You must confirm the removal',
        ];
    }
}
