<?php

namespace App\Http\Requests\FieldLayout;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class EditFieldLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit field layout');
    }

    public function rules(): array
    {
        return [
            'id'   => ['required', 'integer', 'exists:field_layouts,id'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
