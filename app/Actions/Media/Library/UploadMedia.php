<?php

namespace App\Actions\Media\Library;

use App\Actions\AbstractAction;
use App\Http\Requests\FormRequest;
use App\Models\Media;
use App\Models\Media\Library as LibraryModel;

class UploadMedia extends AbstractAction
{
    public function upload(FormRequest $request, LibraryModel $library): Media
    {
        $media = app('media-service')->upload($library, $request->file('file'), [
            'name' => $request->input('name'),
        ]);

        if (!empty($request->input('categories'))) {
            $media->categories()->sync($request->input('categories'));
        }

        return $media;
    }
}
