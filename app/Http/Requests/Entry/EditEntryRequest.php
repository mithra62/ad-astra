<?php

namespace App\Http\Requests\Entry;

use App\Http\Requests\FormRequest;
use App\Models\Entry;
use App\Models\EntryGroup;
use Illuminate\Support\Facades\Auth;

class EditEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit entry');
    }

    public function rules(): array
    {
        $group = Entry::find($this->route()->parameter('entry'));
        $schema = EntryGroup::resolvedFields($group->entryGroup()->first()->id);
        return array_merge(
            [
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


    /**
     * @return array
     */
    public function attributes(): array
    {
        $group = Entry::find($this->route()->parameter('entry'));
        $schema = EntryGroup::resolvedFields($group->entryGroup()->first()->id);
        return $this->schemaFieldAttributes($schema);
    }
}
