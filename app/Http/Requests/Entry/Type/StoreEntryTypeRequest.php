<?php

namespace App\Http\Requests\Entry\Type;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreEntryTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create entry type');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:255'],
            'entry_behavior_id' => ['nullable', 'integer', 'exists:entry_behaviors,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'field_layout_id' => ['nullable', 'integer', 'exists:field_layouts,id'],
            'has_entry_tree' => ['nullable', 'boolean'],
            'max_depth' => ['nullable', 'integer', 'min:0', 'max:10'],
            'allowed_parent_types' => 'nullable|array',
            'default_template' => 'nullable|string|max:255',
        ];
    }
}
