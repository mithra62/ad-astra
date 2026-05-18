<?php

namespace App\Http\Requests\Status\Group;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditStatusGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit status');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:255', Rule::unique('status_groups')->ignore($this->route('group'))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
