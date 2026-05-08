<?php

namespace App\Http\Requests\Media;

use App\Http\Requests\FormRequest;

class DeleteMediaRequest extends FormRequest
{
    public function rules(): array
    {
        return []; // No body expected; authorization is handled by middleware.
    }
}
