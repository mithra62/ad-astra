<?php

namespace App\Http\Requests\Entry;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\EntryGroup;

class StoreEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create entry');
    }

    public function rules(): array
    {
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
            $this->schemaFieldRules()
        );
    }

    public function messages(): array
    {
        return [
            'type_handle.required' => 'An entry type is required.',
            'type_handle.exists' => 'The selected entry type is invalid.',
            'title.required' => 'A title is required.',
        ];
    }

    public function schemaFieldRules(): array
    {
        $rules = [];
        $schema = EntryGroup::resolved($this->route()->parameter('group_id'));

        if (! $schema->fieldLayout) {
            return $rules;
        }

        foreach ($schema->fieldLayout->tabs as $tab) {
            foreach ($tab->elements as $element) {
                $field = $element->field;
                $key = "fields.{$field->handle}";
                $fieldRules = $element->required ? ['required'] : ['nullable'];

                $rules[$key] = array_merge($fieldRules, $field->fieldType->instance()->getRules());
            }
        }

        return $rules;
    }
}
