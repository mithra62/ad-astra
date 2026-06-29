<?php

namespace App\Http\Requests\Entry;

use App\Http\Requests\FormRequest;
use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Rules\CategoryAttachedToGroupable;
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
        $groupSchema = EntryGroup::resolvedFields($entry->entry_group_id);
        $typeSchema = EntryType::resolvedFields($entry->entry_type_id);

        return array_merge(
            [
                'title' => [
                    'required',
                    'string',
                    'max:255'
                ],
                'handle' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('entries', 'handle')
                        ->where(fn ($query) => $query->where('entry_group_id', $entry->entry_group_id))
                        ->ignore($entry->id),
                ],
                'status' => [
                    'nullable',
                    'string',
                    'max:100',
                    Rule::exists('statuses', 'handle')->where(
                        fn ($query) => $query->where('status_group_id', $entry->entryGroup->status_group_id)
                    ),
                ],
                'published_at' => [
                    'nullable',
                    'date'
                ],
                'authors' => [
                    'nullable',
                    'array'
                ],
                'authors.*' => [
                    'integer',
                    Rule::exists('entry_authors', 'user_id')->where('status', 'active')
                ],
                'categories' => [
                    'nullable',
                    'array'
                ],
                'categories.*' => [
                    'integer',
                    new CategoryAttachedToGroupable($entry->entryGroup),
                ],
                'fields' => [
                    'nullable',
                    'array'
                ],
                'parent_entry_id' => [
                    'nullable',
                    'integer',
                    'exists:entries,id'
                ],
                'uri' => [
                    'nullable',
                    'string',
                    'max:255',
                    'unique:entry_trees,uri'
                ],
                'depth' => [
                    'nullable',
                    'integer', 'min:0'
                ],
                'sort_order' => [
                    'nullable',
                    'integer', 'min:0'
                ],
                'template' => [
                    'nullable',
                    'string', 'max:255'
                ],
                'is_home' => [
                    'nullable',
                    'boolean'
                ],
                'redirect_url' => [
                    'nullable',
                    'string',
                    'prohibited_if:is_home,true',
                    'url:http,https',
                    'max:2048',
                ],
                'redirect_status' => [
                    'nullable',
                    'integer',
                    'in:301,302,307,308'
                ],
            ],
            $this->schemaFieldRules($groupSchema),
            $this->schemaFieldRules($typeSchema)
        );
    }

    public function attributes(): array
    {
        $entry = Entry::query()->findOrFail($this->route()->parameter('entry'));
        $groupSchema = EntryGroup::resolvedFields($entry->entry_group_id);
        $typeSchema = EntryType::resolvedFields($entry->entry_type_id);

        return array_merge(
            $this->schemaFieldAttributes($groupSchema),
            $this->schemaFieldAttributes($typeSchema)
        );
    }
}
