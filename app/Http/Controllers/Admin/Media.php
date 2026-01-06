<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Media\Library\UploadMediaRequest;
use App\Models\Media\Library as LibraryModel;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class Media extends Controller
{
    /**
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function upload(UploadMediaRequest $request, string $id)
    {
        $library = LibraryModel::find($id);
        if (!$library instanceof LibraryModel) {
            abort(404);
        }

        $path = $request->file('file', $library->adapter);
        $media = $library->addMedia($path)->toMediaCollection($library->slug);
        $media->library_id = $library->id;
        $media->save();
        exit;
    }
}
