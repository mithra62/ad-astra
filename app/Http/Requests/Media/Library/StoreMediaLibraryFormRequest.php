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
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_groups')->ignore($this->data('id')),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_groups')->ignore($this->data('id')),
            ],
        ];
    }
}
