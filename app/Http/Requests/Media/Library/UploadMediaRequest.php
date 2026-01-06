<?php

namespace App\Http\Requests\Media\Library;

use App\Http\Requests\FormRequest;
use App\Models\Media\Library as LibraryModel;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $library_id = $this->route()->parameter('library_id');
        $library = LibraryModel::find($library_id);
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'categories' => [
                'array',
            ],
            'file' => [
                'file',
                'required',
            ],
        ];

        if ($library instanceof LibraryModel) {
            $rules['file'][] = 'mimetypes:' . app('files-service')->compileMimeTypes($library->allowed_types);
            $rules['file'][] = 'max:' . app('files-service')->convertMbToBytes($library->max_size);
        }

        return $rules;
    }
}
