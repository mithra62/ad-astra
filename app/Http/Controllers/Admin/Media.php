<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Media\EditMediaRequest;
use App\Http\Requests\Media\DeleteMediaRequest;
use App\Http\Requests\Media\StoreMediaFormRequest;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Media\Library as LibraryModel;
use App\Models\Media as MediaModel;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class Media extends Controller
{
    public function index()
    {

    }

    public function create(string $library_id)
    {
        $library = LibraryModel::find($library_id);
        $data = [
            'library' => $library,
        ];
        return $this->view('media.create', $data);
    }

    public function store(StoreMediaFormRequest $request)
    {

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
}
