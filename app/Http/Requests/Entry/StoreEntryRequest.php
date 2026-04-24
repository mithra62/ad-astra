<?php

namespace App\Http\Requests\Entry;

use App\Http\Requests\FormRequest;
use App\Models\EntryGroup;
use Illuminate\Support\Facades\Auth;

class StoreEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create entry');
    }

    public function rules(): array
    {
        $schema = EntryGroup::resolvedFields($this->route()->parameter('group_id'));
        return array_merge(
            [
                'type_handle' => ['required', 'string', 'exists:entry_types,handle'],
                'title' => ['required', 'string', 'max:255'],
                'handle' => ['nullable', 'string', 'max:255'],
                'status' => ['nullable', 'string', 'max:100'],
                'published_at' => ['nullable', 'date'],
                'authors' => ['nullable', 'array'],
                'authors.*' => ['integer', 'exists:users,id'],
                'categories' => ['nullable', 'array'],
                'categories.*' => ['integer', 'exists:categories,id'],
                'fields' => ['nullable', 'array'],
            ],
            $this->schemaFieldRules($schema)
        );
    }

    public function messages(): array
    {
        $schema = EntryGroup::resolvedFields($this->route()->parameter('group_id'));
        return $this->schemaFieldMessages($schema);
    }

    /**
     * @return array
     */
    public function attributes(): array
    {
        $schema = EntryGroup::resolvedFields($this->route()->parameter('group_id'));
        return $this->schemaFieldAttributes($schema);
    }
}
