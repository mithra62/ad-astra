<?php

namespace App\Http\Controllers\Admin\Media;

use App\Actions\Media\Library\CreateNewMediaLibrary;
use App\Actions\Media\Library\DeleteMediaLibrary;
use App\Actions\Media\Library\EditMediaLibrary;
use App\Actions\Media\Library\UploadMedia;
use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\Media\Library\DeleteMediaLibraryRequest;
use App\Http\Requests\Media\Library\EditMediaLibraryRequest;
use App\Http\Requests\Media\Library\StoreMediaLibraryFormRequest;
use App\Http\Requests\Media\Library\UploadMediaRequest;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Field\Group as FieldGroup;
use App\Models\Media\Library as LibraryModel;

class Library extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $libraries = LibraryModel::paginate(20);
        return $this->view('media.libraries.index', ['libraries' => $libraries]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMediaLibraryFormRequest $request)
    {
        $creator = app(CreateNewMediaLibrary::class);
        $library = $creator->create($request->all());
        return redirect()->route('media.libraries.show', $library->id)->with('success', trans('media.library.created'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $files = app('files-service');
        $field_groups = FieldGroup::all();
        $category_groups = CategoryGroup::all();
        $data = [
            'category_groups' => $category_groups,
            'field_groups' => $field_groups,
            'disks' => config('filesystems.disks'),
            'allowed_types' => $files->getAllowedMimeTypes(),
        ];
        return $this->view('media.libraries.create', $data);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $library = LibraryModel::find($id);
        if (!$library instanceof LibraryModel) {
            abort(404);
        }

        $media = $library->media()->paginate(20);

        $data = [
            'library' => $library,
            'libraries' => LibraryModel::all(),
            'media' => $media,
        ];

        return $this->view('media.libraries.view', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditMediaLibraryRequest $request, string $id)
    {
        $library = LibraryModel::find($id);
        if ($library instanceof LibraryModel) {
            $editor = app(EditMediaLibrary::class);
            $editor->edit($library, $request->validated());
            return redirect()->route('media.libraries')->with('success', trans('media.library.updated'));
        }

        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $library = LibraryModel::with('categoryGroups', 'fieldGroups')->find($id);
        if (!$library instanceof LibraryModel) {
            abort(404);
        }

        $files = app('files-service');
        $category_groups = CategoryGroup::all();
        $field_groups = FieldGroup::all();
        $data = [
            'library' => $library,
            'category_groups' => $category_groups,
            'field_groups' => $field_groups,
            'disks' => config('filesystems.disks'),
            'allowed_types' => $files->getAllowedMimeTypes(),
        ];

        return $this->view('media.libraries.edit', $data);
    }

    public function destroy(DeleteMediaLibraryRequest $request, string $id)
    {
        $library = LibraryModel::find($id);
        if ($library instanceof LibraryModel) {
            $deleter = app(DeleteMediaLibrary::class);
            $deleter->delete($library);
            return redirect()->route('media.libraries')->with('success', trans('media.library.deleted'));
        }

        return redirect()->route('media.libraries')->with('failure', trans('media.library.not_found'));
    }

    public function confirm(string $id)
    {
        $library = LibraryModel::find($id);
        if (!$library instanceof LibraryModel) {
            return redirect()->route('media.libraries')->with('failure', trans('media.library.not_found'));
        }

        return $this->view('media.libraries.delete', ['library' => $library]);
    }

    public function upload(UploadMediaRequest $request, string $id)
    {
        $library = LibraryModel::find($id);
        if (!$library instanceof LibraryModel) {
            if ($request->expectsJson()) {
                return response()->json(['error' => trans('media.library.not_found')], 404);
            }
            abort(404);
        }

        $media = app(UploadMedia::class)->upload($request, $library);

        if ($request->expectsJson()) {
            return $media
                ? response()->json(['id' => $media->id, 'name' => $media->original_name, 'url' => $media->url()])
                : response()->json(['error' => trans('media.upload_failed')], 422);
        }

        return $media
            ? redirect()->route('media.show', $media)->with('success', trans('media.uploaded'))
            : redirect()->route('media.libraries.show', $library)->with('failure', trans('media.upload_failed'));
    }
}
