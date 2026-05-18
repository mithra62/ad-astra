<?php

namespace App\Http\Requests\Status;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeleteStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('delete status');
    }

    public function rules(): array
    {
        return [
            'confirm_removal' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_removal.required' => 'You must confirm the removal.',
        ];
    }
}
