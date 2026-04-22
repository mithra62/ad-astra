<?php

namespace App\Http\Requests\Entry\Type;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreEntryTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create entry type');
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'handle'         => ['required', 'string', 'max:255'],
            'class'          => ['required', 'string', 'max:255'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'field_layout_id'=> ['nullable', 'integer', 'exists:field_layouts,id'],
        ];
    }
}
