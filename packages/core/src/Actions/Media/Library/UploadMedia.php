<?php

namespace AdAstra\Actions\Media\Library;

use AdAstra\Actions\AbstractAction;
use AdAstra\Facades\MediaStorage;
use AdAstra\Http\Requests\FormRequest;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Library as LibraryModel;

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
