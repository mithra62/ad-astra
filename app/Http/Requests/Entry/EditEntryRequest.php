<?php

namespace App\Http\Requests\Entry;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class EditEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit entry');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'handle' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'published_at' => ['nullable', 'date'],
            'authors' => ['nullable', 'array'],
            'authors.*' => ['integer', 'exists:users,id'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'fields' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'A title is required.',
        ];
    }
}
