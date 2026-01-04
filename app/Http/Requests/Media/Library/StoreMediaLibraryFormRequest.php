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

    /**
     * @return string[]
     */
    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('media_libraries')->ignore($this->route()->parameter('library')),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('media_libraries')->ignore($this->route()->parameter('library')),
            ],
            'storage' => [
                'required',
                'string',
            ],
            'adapter_settings' => [
                'required',
                'array',
            ],
            'max_size' => [
                'required',
                'numeric',
            ],
            'category_groups' => [
                'array',
            ],
        ];

        if ($this->data('storage') == 'local') {
            $adaptor_rules = [
                'adapter_settings.local.url' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'adapter_settings.local.path' => [
                    'required',
                    'string',
                    'max:255',
                ],
            ];

            $rules = array_merge($rules, $adaptor_rules);
        }

        return $rules;
    }
}
