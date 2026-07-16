<?php

namespace AdAstra\Http\Requests\Entry;

use AdAstra\Http\Requests\FormRequest;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType;
use AdAstra\Rules\CategoryAttachedToGroupable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create entry');
    }

    public function rules(): array
    {
        $group = EntryGroup::query()
            ->with('statusGroup')
            ->findOrFail($this->route()->parameter('group_id'));
        $groupSchema = EntryGroup::resolvedFields($group->id);
        $typeSchema = $this->resolveEntryTypeSchema($group->id);

        return array_merge(
            [
                'type_handle' => [
                    'required',
                    'string',
                    Rule::exists('entry_types', 'handle')->where(
                        fn ($q) => $q->where('entry_group_id', $this->route()->parameter('group_id'))
                    ),
                ],
                'title' => [
                    'required',
                    'string',
                    'max:255'
                ],
                'handle' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('entries', 'handle')->where(
                        fn ($query) => $query->where('entry_group_id', $group->id)
                    ),
                ],
                'status' => [
                    'nullable',
                    'string',
                    'max:100',
                    Rule::exists('statuses', 'handle')->where(
                        fn ($query) => $query->where('status_group_id', $group->status_group_id)
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
                    new CategoryAttachedToGroupable($group),
                ],
                'fields' => [
                    'nullable',
                    'array'
                ],
                'parent_entry_id' => [
                    'nullable',
                    'integer',
                    // The parent entry must already have an Entry Tree node —
                    // otherwise placement would silently fall back to root.
                    Rule::exists('entry_trees', 'entry_id'),
                ],
                'uri' => [
                    'nullable',
                    'string',
                    'max:255',
                    'unique:entry_trees,uri'
                ],
                'depth' => [
                    'nullable',
                    'integer',
                    'min:0'
                ],
                'template' => [
                    'nullable',
                    'string',
                    'max:255'
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

    private function resolveEntryTypeSchema(int $groupId): ?EntryType
    {
        $handle = $this->input('type_handle');
        if (!$handle) {
            return null;
        }

        return EntryType::query()
            ->with('fieldLayout.tabs.elements.field')
            ->where('handle', $handle)
            ->where('entry_group_id', $groupId)
            ->first();
    }

    public function messages(): array
    {
        $groupSchema = EntryGroup::resolvedFields($this->route()->parameter('group_id'));
        $typeSchema = $this->resolveEntryTypeSchema($this->route()->parameter('group_id'));

        return array_merge(
            $this->schemaFieldMessages($groupSchema),
            $this->schemaFieldMessages($typeSchema)
        );
    }

    public function attributes(): array
    {
        $groupSchema = EntryGroup::resolvedFields($this->route()->parameter('group_id'));
        $typeSchema = $this->resolveEntryTypeSchema($this->route()->parameter('group_id'));

        return array_merge(
            $this->schemaFieldAttributes($groupSchema),
            $this->schemaFieldAttributes($typeSchema)
        );
    }
}
