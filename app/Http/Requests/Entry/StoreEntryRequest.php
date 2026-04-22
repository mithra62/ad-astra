<?php

namespace App\Http\Requests\Entry;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create entry');
    }

    public function rules(): array
    {
        return [
            'type_handle'  => ['required', 'string', 'exists:entry_types,handle'],
            'title'        => ['required', 'string', 'max:255'],
            'slug'         => ['nullable', 'string', 'max:255'],
            'status'       => ['nullable', 'string', 'max:100'],
            'published_at' => ['nullable', 'date'],
            'authors'      => ['nullable', 'array'],
            'authors.*'    => ['integer', 'exists:users,id'],
            'categories'   => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'fields'       => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'type_handle.required' => 'An entry type is required.',
            'type_handle.exists'   => 'The selected entry type is invalid.',
            'title.required'       => 'A title is required.',
        ];
    }
}
