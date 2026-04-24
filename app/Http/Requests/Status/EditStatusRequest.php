<?php

namespace App\Http\Requests\Status;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit status');
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'handle'     => ['required', 'string', 'max:255', Rule::unique('statuses')->ignore($this->route('status'))],
            'color'      => ['nullable', 'string', 'max:20'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
