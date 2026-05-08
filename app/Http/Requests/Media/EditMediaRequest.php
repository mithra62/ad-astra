<?php

namespace App\Http\Requests\Media;

use App\Http\Requests\FormRequest;
use App\Models\Media;
use App\Models\Media\Library as MediaLibrary;

class EditMediaRequest extends FormRequest
{
    public function rules(): array
    {
        $media = Media::find($this->route()->parameter('media_item'));
        $schema = MediaLibrary::resolvedFields($media->library_id);
        return array_merge(
            [
                'name' => [
                    'required',
                    'sometimes',
                    'string',
                    'max:255',
                ],
            ],
            $this->schemaFieldRules($schema)
        );
    }
    public function messages(): array
    {
        $media = Media::find($this->route()->parameter('media_item'));
        $schema = MediaLibrary::resolvedFields($media->library_id);
        return $this->schemaFieldMessages($schema);
    }

    /**
     * @return array
     */
    public function attributes(): array
    {
        $media = Media::find($this->route()->parameter('media_item'));
        $schema = MediaLibrary::resolvedFields($media->library_id);
        return $this->schemaFieldAttributes($schema);
    }
}
