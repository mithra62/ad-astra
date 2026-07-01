<?php

namespace AdAstra\Http\Requests\Entry;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeleteEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('delete entry');
    }

    public function rules(): array
    {
        return [
            'confirm_removal' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_removal.required' => 'You must confirm removal.',
        ];
    }
}
