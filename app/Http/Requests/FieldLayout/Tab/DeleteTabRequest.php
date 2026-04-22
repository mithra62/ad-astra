<?php

namespace App\Http\Requests\FieldLayout\Tab;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeleteTabRequest extends FormRequest
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
            'confirm_removal.accepted'  => 'You must confirm the removal.',
        ];
    }
}
