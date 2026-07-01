<?php

namespace AdAstra\Http\Requests\Media;

use AdAstra\Http\Requests\FormRequest;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Library as MediaLibrary;
use Illuminate\Validation\Rule;

class EditMediaRequest extends FormRequest
{
    private ?MediaLibrary $resolvedSchema = null;
    private bool $schemaResolved = false;

    public function rules(): array
    {
        $schema = $this->resolvedSchema();

        return array_merge(
            [
                'name' => ['required', 'sometimes', 'string', 'max:255'],
                'status' => [
                    'nullable',
                    'string',
                    'max:100',
                    Rule::exists('statuses', 'handle')->where(
                        fn ($query) => $query->where('status_group_id', $schema?->status_group_id)
                    ),
                ],
            ],
            $this->schemaFieldRules($schema)
        );
    }

    public function messages(): array
    {
        return $this->schemaFieldMessages($this->resolvedSchema());
    }

    public function attributes(): array
    {
        return $this->schemaFieldAttributes($this->resolvedSchema());
    }

    private function resolvedSchema(): ?MediaLibrary
    {
        if (!$this->schemaResolved) {
            $media = Media::find($this->route()->parameter('media_item'));
            $this->resolvedSchema = ($media && $media->library_id)
                ? MediaLibrary::with('fieldLayout.tabs.elements.field.fieldType')
                              ->find($media->library_id)
                : null;
            $this->schemaResolved = true;
        }

        return $this->resolvedSchema;
    }
}
