<?php

namespace App\Http\Controllers\Admin;

use App\Actions\FieldLayout\CreateNewFieldLayout;
use App\Actions\FieldLayout\EditFieldLayout;
use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\FieldLayout\DeleteFieldLayoutRequest;
use App\Http\Requests\FieldLayout\EditFieldLayoutRequest;
use App\Http\Requests\FieldLayout\StoreFieldLayoutRequest;
use App\Models\FieldLayout as FieldLayoutModel;

class FieldLayout extends Controller
{
    public function index()
    {
        $layouts = FieldLayoutModel::withCount(['tabs'])
            ->orderBy('name')
            ->paginate(20);

        return $this->view('field-layouts.index', ['layouts' => $layouts]);
    }

    public function create()
    {
        return $this->view('field-layouts.create');
    }

    public function store(StoreFieldLayoutRequest $request)
    {
        $creator = app(CreateNewFieldLayout::class);
        $layout  = $creator->create($request->all());

        return redirect()
            ->route('field-layouts.edit', $layout->id)
            ->with('success', trans('field_layout.created'));
    }

    public function edit(string $id)
    {
        $layout = FieldLayoutModel::with([
            'tabs.elements.field.fieldType',
            'entryGroups',
            'entryTypes.entryGroup',
        ])->find($id);

        if (! $layout instanceof FieldLayoutModel) {
            abort(404);
        }

        return $this->view('field-layouts.edit', ['layout' => $layout]);
    }

    public function update(EditFieldLayoutRequest $request, string $id)
    {
        $layout = FieldLayoutModel::find($id);
        if (! $layout instanceof FieldLayoutModel) {
            abort(404);
        }

        $editor = app(EditFieldLayout::class);
        $editor->edit($layout, $request->all());

        return redirect()
            ->route('field-layouts.edit', $id)
            ->with('success', trans('field_layout.updated'));
    }

    public function confirm(string $id)
    {
        $layout = FieldLayoutModel::withCount('tabs')->find($id);
        if (! $layout instanceof FieldLayoutModel) {
            return redirect()->route('field-layouts')->with('failure', trans('field_layout.not_found'));
        }

        return $this->view('field-layouts.delete', ['layout' => $layout]);
    }

    public function destroy(DeleteFieldLayoutRequest $request, string $id)
    {
        $layout = FieldLayoutModel::find($id);
        if ($layout instanceof FieldLayoutModel) {
            $layout->delete();
            return redirect()
                ->route('field-layouts')
                ->with('success', trans('field_layout.deleted'));
        }

        return redirect()
            ->route('field-layouts')
            ->with('failure', trans('field_layout.not_found'));
    }
}
