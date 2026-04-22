<?php

namespace App\Http\Requests\Entry\Group;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditEntryGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit entry group');
    }

    public function rules(): array
    {
        return [
            'id'               => ['required'],
            'name'             => ['required', 'string', 'max:255'],
            'handle'           => ['required', 'string', 'max:255', Rule::unique('entry_groups', 'handle')->ignore($this->route('id'))],
            'description'      => ['nullable', 'string'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
            'status_group_id'  => ['nullable', 'integer', 'exists:status_groups,id'],
            'field_layout_id'  => ['nullable', 'integer', 'exists:field_layouts,id'],
            'category_groups'  => ['nullable', 'array'],
            'category_groups.*'=> ['integer', 'exists:category_groups,id'],
            'field_groups'     => ['nullable', 'array'],
            'field_groups.*'   => ['integer', 'exists:field_groups,id'],
        ];
    }
}
