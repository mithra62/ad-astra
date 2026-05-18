<?php

namespace App\Http\Requests\FieldLayout\Tab\Element;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreElementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit field layout');
    }

    public function rules(): array
    {
        return [
            'field_id' => ['required', 'integer', 'exists:fields,id'],
            'required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
