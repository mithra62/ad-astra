<?php

namespace App\Http\Requests\Entry;

use App\Http\Requests\FormRequest;
use App\Models\Entry;
use App\Models\EntryGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit entry');
    }

    public function rules(): array
    {
        $entry = Entry::query()
            ->with('entryGroup.statusGroup')
            ->findOrFail($this->route()->parameter('entry'));
        $schema = EntryGroup::resolvedFields($entry->entry_group_id);

        return array_merge(
            [
                'title' => ['required', 'string', 'max:255'],
                'handle' => ['nullable', 'string', 'max:255'],
                'status' => [
                    'nullable',
                    'string',
                    'max:100',
                    Rule::exists('statuses', 'handle')->where(
                        fn ($query) => $query->where('status_group_id', $entry->entryGroup->status_group_id)
                    ),
                ],
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


    /**
     * @return array
     */
    public function attributes(): array
    {
        $entry = Entry::query()->findOrFail($this->route()->parameter('entry'));
        $schema = EntryGroup::resolvedFields($entry->entry_group_id);

        return $this->schemaFieldAttributes($schema);
    }
}
