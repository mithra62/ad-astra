<?php

namespace App\Http\Requests\Media\Library;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreMediaLibraryFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create media library');
    }

    public function rules(): array
    {
        $library = $this->route()->parameter('library');
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('media_libraries', 'name')->ignore($library),
            ],
            'handle' => [
                'required',
                'string',
                'max:255',
                Rule::unique('media_libraries', 'handle')->ignore($library),
            ],
            'adapter' => [
                'required',
                'string',
                'max:50',
            ],

            // adapter_settings is nullable — local adapter needs no extra config by default.
            'adapter_settings'       => ['nullable', 'array'],
            //'adapter_settings.*.url' => ['sometimes', 'string', 'max:255'],

            // allowed_types null = accept any MIME type.
            'allowed_types'   => ['nullable', 'array'],
            'allowed_types.*' => ['string'],

            // max_size in MB; 0 = unlimited.
            'max_size'   => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            // Relationship IDs — both optional.
            'category_groups'   => ['nullable', 'array'],
            'category_groups.*' => ['integer', 'exists:category_groups,id'],
            'field_groups'      => ['nullable', 'array'],
            'field_groups.*'    => ['integer', 'exists:field_groups,id'],
        ];

        // Require an explicit URL when storing files on a non-local adapter
        // that needs a public base URL (e.g. S3 custom domain).
        if ($this->input('adapter') !== 'local') {
            //$rules['adapter_settings.url'] = ['required', 'url', 'max:255'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'adapter_settings.url' => 'Adapter Base URL',
            'allowed_types'        => 'Allowed File Types',
            'max_size'             => 'Max File Size (MB)',
        ];
    }
}
