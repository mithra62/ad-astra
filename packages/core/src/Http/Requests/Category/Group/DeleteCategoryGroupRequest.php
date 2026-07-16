<?php

namespace AdAstra\Http\Requests\Category\Group;

use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeleteCategoryGroupRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('delete category group');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'confirm_removal' => 'required',
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'confirm_removal.required' => 'You must confirm the removal',
        ];
    }
}
