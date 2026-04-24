<?php

namespace App\Http\Requests\FieldLayout;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreFieldLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create field layout');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
