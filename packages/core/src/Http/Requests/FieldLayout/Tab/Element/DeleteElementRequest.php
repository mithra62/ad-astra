<?php

namespace AdAstra\Http\Requests\FieldLayout\Tab\Element;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeleteElementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit field layout');
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
            'confirm_removal.required' => 'You must confirm the removal.',
            'confirm_removal.accepted' => 'You must confirm the removal.',
        ];
    }
}
