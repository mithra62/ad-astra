<?php

namespace AdAstra\Http\Controllers\Admin\Entry;

use AdAstra\Facades\EntryTypes;
use AdAstra\Http\Controllers\Admin\Controller;
use AdAstra\Http\Requests\Entry\Type\DeleteEntryTypeRequest;
use AdAstra\Http\Requests\Entry\Type\EditEntryTypeRequest;
use AdAstra\Http\Requests\Entry\Type\StoreEntryTypeRequest;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType as EntryTypeModel;
use AdAstra\Models\FieldLayout;

class Type extends Controller
{
    public function index()
    {
        $types = EntryTypeModel::with(['entryBehavior', 'entryGroup'])
            ->orderBy('name')
            ->paginate($this->total_per_page);

        return $this->view('entries.types.index', ['types' => $types]);
    }

    public function create()
    {
        return $this->view('entries.types.create', $this->formData());
    }

    public function store(StoreEntryTypeRequest $request)
    {
        $type = EntryTypes::create($request->validated());

        return redirect()
            ->route('entries.types.edit', $type->id)
            ->with('success', trans('entry.type.created'));
    }

    public function edit(string $id)
    {
        $type = EntryTypeModel::with(['entryBehavior', 'fieldLayout'])->find($id);

        if (!$type instanceof EntryTypeModel) {
            abort(404);
        }

        return $this->view('entries.types.edit', array_merge(
            $this->formData(),
            ['type' => $type]
        ));
    }

    public function update(EditEntryTypeRequest $request, string $id)
    {
        $type = EntryTypes::find((int)$id);

        if (!$type instanceof EntryTypeModel) {
            abort(404);
        }

        EntryTypes::update($type, $request->validated());

        return redirect()
            ->route('entries.types.edit', $id)
            ->with('success', trans('entry.type.updated'));
    }

    public function destroy(DeleteEntryTypeRequest $request, string $id)
    {
        $type = EntryTypes::find((int)$id);

        if (!$type instanceof EntryTypeModel) {
            return redirect()
                ->route('entries.types')
                ->with('failure', trans('entry.type.not_found'));
        }

        EntryTypes::delete($type);

        return redirect()
            ->route('entries.types')
            ->with('success', trans('entry.type.deleted'));
    }

    public function confirm(string $id)
    {
        $type = EntryTypeModel::with(['entryBehavior', 'entryGroup'])->find($id);

        if (!$type instanceof EntryTypeModel) {
            abort(404);
        }

        return $this->view('entries.types.delete', ['type' => $type]);
    }

    private function formData(): array
    {
        return [
            'behaviors' => EntryBehavior::orderBy('name')->get(),
            'field_layouts' => FieldLayout::orderBy('name')->get(),
        ];
    }
}
