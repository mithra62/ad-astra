<?php

namespace AdAstra\Http\Requests\Media;

use AdAstra\Http\Requests\FormRequest;

class DeleteMediaRequest extends FormRequest
{
    public function rules(): array
    {
        return []; // No body expected; authorization is handled by middleware.
    }
}
