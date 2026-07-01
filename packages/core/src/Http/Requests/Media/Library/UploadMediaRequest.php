<?php

namespace AdAstra\Http\Requests\Media\Library;

use AdAstra\Http\Requests\FormRequest;
use AdAstra\Models\Media\Library as LibraryModel;
use AdAstra\Rules\CategoryAttachedToGroupable;
use Illuminate\Support\Facades\Auth;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->can('upload media') === true;
    }

    public function rules(): array
    {
        $library = $this->resolveLibrary();

        $fileRules = ['required', 'file'];

        if ($library instanceof LibraryModel) {
            // MIME type restriction — null allowed_types means accept anything.
            if (!empty($library->allowed_types)) {
                $fileRules[] = 'mimetypes:' . implode(',', $library->allowed_types);
            }

            // max_size is stored in MB; Laravel's max: rule expects kilobytes.
            if ($library->max_size > 0) {
                $fileRules[] = 'max:' . ($library->max_size * 1024);
            }
        }

        return [
            'file' => $fileRules,
            'name' => ['nullable', 'string', 'max:255'],
            'categories' => ['nullable', 'array'],
            'categories.*' => [
                'integer',
                $library instanceof LibraryModel ? new CategoryAttachedToGroupable($library) : 'exists:categories,id',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'uploaded file',
        ];
    }

    /**
     * Resolve the library from the route — supports both 'library' and
     * 'library_id' as route parameter names.
     */
    private function resolveLibrary(): ?LibraryModel
    {
        $param = $this->route()->parameter('library')
            ?? $this->route()->parameter('library_id');

        if ($param instanceof LibraryModel) {
            return $param;
        }

        return $param ? LibraryModel::find($param) : null;
    }
}
