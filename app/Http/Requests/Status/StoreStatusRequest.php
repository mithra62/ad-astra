<?php

namespace App\Http\Requests\Status;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create status');
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'handle'          => ['required', 'string', 'max:255', Rule::unique('statuses')],
            'color'           => ['nullable', 'string', 'max:20'],
            'is_default'      => ['nullable', 'boolean'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
            'status_group_id' => ['required', 'integer', 'exists:status_groups,id'],
        ];
    }
}
