<?php

namespace App\Http\Requests\Status;

use App\Http\Requests\FormRequest;
use App\Rules\Status\UniqueHandleByGroup;
use Illuminate\Support\Facades\Auth;

class EditStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit status');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:255', new UniqueHandleByGroup(['status_id' => $this->route('status')])],
            'color' => ['nullable', 'string', 'max:20'],
            'is_default' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
