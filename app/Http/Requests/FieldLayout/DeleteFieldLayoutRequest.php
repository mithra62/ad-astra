<?php

namespace App\Http\Requests\FieldLayout;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeleteFieldLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('delete field layout');
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
