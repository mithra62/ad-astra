<?php

namespace App\Http\Requests\Entry\Group;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreEntryGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create entry group');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:255', Rule::unique('entry_groups', 'handle')],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status_group_id' => ['required', 'integer', 'exists:status_groups,id'],
            'field_layout_id' => ['nullable', 'integer', 'exists:field_layouts,id'],
            'category_groups' => ['nullable', 'array'],
            'category_groups.*' => ['integer', 'exists:category_groups,id'],
            'field_groups' => ['nullable', 'array'],
            'field_groups.*' => ['integer', 'exists:field_groups,id'],
            'entry_type_ids' => ['nullable', 'array'],
            'entry_type_ids.*' => ['integer', 'exists:entry_types,id'],
        ];
    }
}
