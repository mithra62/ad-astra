<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Media\DeleteMedia as DeleteMediaAction;
use App\Http\Requests\Media\DeleteMediaRequest;
use App\Http\Requests\Media\EditMediaRequest;
use App\Models\Media as MediaModel;
use App\Models\Media\Library as LibraryModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use App\Actions\Media\EditMedia as EditMediaAction;

class Media extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $media = MediaModel::paginate(20);
        return $this->view('media.index', compact('media'));
    }

    public function create(string $library_id): \Illuminate\View\View|RedirectResponse
    {
        $library = LibraryModel::with('categoryGroups')->find($library_id);
        if (!$library instanceof LibraryModel) {
            return redirect()->route('media.libraries')
                ->with('failure', trans('media.library.not_found'));
        }
        return $this->view('media.create', compact('library'));
    }

    public function store(): RedirectResponse
    {
        // Uploads are handled through the Library upload flow (Library::upload).
        return redirect()->route('media.libraries');
    }

    public function show(string $id): \Illuminate\View\View
    {
        $media = MediaModel::findOrFail($id);
        return $this->view('media.show', compact('media'));
    }

    public function edit(string $id): \Illuminate\View\View
    {
        $media = MediaModel::findOrFail($id);
        return $this->view('media.edit', compact('media'));
    }

    public function update(EditMediaRequest $request, string $id): RedirectResponse
    {
        $media = MediaModel::with([
            'library.fieldLayout.tabs.elements.field.fieldType',
        ])->findOrFail($id);
        $editor = app(EditMediaAction::class);
        $media = $editor->edit($media, $request->validated());
        return redirect()->route('media.show', $media->id)
            ->with('success', trans('media.updated'));
    }

    public function confirm(string $id): \Illuminate\View\View|RedirectResponse
    {
        $media = MediaModel::find($id);
        if (!$media instanceof MediaModel) {
            return redirect()->route('media.index')
                ->with('failure', trans('media.not_found'));
        }
        return $this->view('media.confirm', compact('media'));
    }

    public function destroy(DeleteMediaRequest $request, string $id): RedirectResponse
    {
        $media = MediaModel::findOrFail($id);
        (new DeleteMediaAction)->delete($media);
        return redirect()->route('media.index')
            ->with('success', trans('media.deleted'));
    }

    public function download(string $id): \Symfony\Component\HttpFoundation\Response
    {
        $media = MediaModel::findOrFail($id);
        if (!Storage::disk($media->disk)->exists($media->path)) {
            abort(404, 'File not found on disk.');
        }
        return Storage::disk($media->disk)->download($media->path, $media->original_name);
    }
}
