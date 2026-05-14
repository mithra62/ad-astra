<?php

namespace App\Http\Requests\Field;

use App\Http\Requests\FormRequest;
use App\Models\Field\Type as FieldType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create field');
    }

    public function rules(): array
    {
        $base = [
            'field_type_id' => [
                'required',
                'integer',
                'exists:field_types,id',
            ],
            'label' => [
                'nullable',
                'string',
                'max:255',
            ],
            'instructions' => [
                'nullable',
                'string',
                'max:255',
            ],
            'hidden' => [
                'nullable',
                'boolean',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('fields')->ignore($this->route()->parameter('field')),
            ],
            'handle' => [
                'required',
                'string',
                'max:255',
                Rule::unique('fields', 'handle')->ignore($this->route()->parameter('field')),
            ],
        ];

        $typeId = $this->input('field_type_id');
        if ($typeId && $type = FieldType::find($typeId)) {
            $base = array_merge($base, $type->instance()->settingsRules());
        }

        return $base;
    }
}
