<?php

namespace App\Http\Requests\Media;

use App\Http\Requests\FormRequest;

class EditMediaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
