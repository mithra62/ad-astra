<?php

namespace App\Http\Requests\Field;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreFieldRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('create field');
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
                Rule::unique('fields')->ignore($this->data('id')),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('fields')->ignore($this->data('id')),
            ],
        ];
    }
}
