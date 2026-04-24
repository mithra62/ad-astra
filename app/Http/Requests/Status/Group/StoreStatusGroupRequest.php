<?php

namespace App\Http\Requests\Status\Group;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreStatusGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create status');
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'handle'     => ['required', 'string', 'max:255', Rule::unique('status_groups')],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
