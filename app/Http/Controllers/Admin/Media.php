<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Media\EditMediaRequest;
use App\Http\Requests\Media\DeleteMediaRequest;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Media\Library as LibraryModel;
use App\Models\Media as MediaModel;

class Media extends Controller
{
    public function index()
    {

    }

    public function create(string $library_id)
    {
        $library = LibraryModel::with('category_groups')->find($library_id);
        $data = [
            'library' => $library,
        ];
        return $this->view('media.create', $data);
    }

    public function show(string $id)
    {

    }

    public function edit(string $id)
    {

    }

    public function update(EditMediaRequest $request, string $id)
    {

    }

    public function destroy(DeleteMediaRequest $request, string $id)
    {

    }

    public function confirm()
    {

    }

    public function download(string $id)
    {
        $media = MediaModel::find($id);
        if(!$media instanceof MediaModel) {
            abort(404);
        }

        return $media;
    }
}
