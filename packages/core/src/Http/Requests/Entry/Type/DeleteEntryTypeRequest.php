<?php

namespace AdAstra\Http\Requests\Entry\Type;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeleteEntryTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('delete entry type');
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
            'confirm_removal.accepted' => 'You must confirm removal.',
        ];
    }
}
