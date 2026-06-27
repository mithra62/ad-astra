<?php

namespace App\Actions\Media\Library;

use App\Actions\AbstractAction;
use App\Facades\MediaStorage;
use App\Http\Requests\FormRequest;
use App\Models\Media;
use App\Models\Media\Library as LibraryModel;

class UploadMedia extends AbstractAction
{
    public function upload(FormRequest $request, LibraryModel $library): Media
    {
        $attributes = array_filter([
            'name' => $request->input('name'),
        ], fn ($v) => $v !== null);

        $media = MediaStorage::upload($library, $request->file('file'), $attributes);

        if (!empty($request->input('categories'))) {
            $media->categories()->sync($request->input('categories'));
        }

        return $media;
    }
}
