<?php

namespace AdAstra\Http\Requests\FieldLayout\Tab;

use AdAstra\Http\Requests\FormRequest;
use AdAstra\Rules\FieldLayout\Tab\UniqueHandleByLayout;
use Illuminate\Support\Facades\Auth;

class EditTabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit field layout');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255'
            ],
            'handle' => [
                'required',
                'string',
                'max:255',
                new UniqueHandleByLayout(['tab_id' => $this->route()->parameter('tab_id')]),
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0'
            ],
        ];
    }
}
