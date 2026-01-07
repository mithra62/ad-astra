<?php
namespace App\Actions\Media\Library;

use App\Actions\AbstractAction;
use App\Models\Media\Library as LibraryModel;
use App\Models\Media AS MediaModel;
use App\Http\Requests\FormRequest;

class UploadMedia extends AbstractAction
{
    public function upload(FormRequest $request, LibraryModel $library)
    {
        $path = $request->file('file', $library->adapter);

        $media = $library->addMedia($path)->toMediaCollection($library->slug);
        $media->library_id = $library->id;
        $media->name = $request->input('name');

        $media->categories()->detach();
        if (!empty($request->input('categories'))) {
            foreach ($request->input('categories') as $cat_group) {
                $media->categories()->attach($cat_group);
            }
        }

        $media->save();

        return $media;
    }
}
