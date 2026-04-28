<?php

namespace App\Http\Controllers\Admin\Entry;

use App\EntryTypes\AbstractEntryType;
use App\Facades\EntryTypes;
use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\Entry\Type\DeleteEntryTypeRequest;
use App\Http\Requests\Entry\Type\EditEntryTypeRequest;
use App\Http\Requests\Entry\Type\StoreEntryTypeRequest;
use App\Models\EntryGroup;
use App\Models\EntryType as EntryTypeModel;
use App\Models\FieldLayout;
use Illuminate\Support\Collection;

class Type extends Controller
{
    public function store(StoreEntryTypeRequest $request, string $group_id)
    {
        $group = EntryGroup::find($group_id);

        if (!$group instanceof EntryGroup) {
            abort(404);
        }

        EntryTypes::create((int)$group_id, $request->validated());

        return redirect()
            ->route('entries.groups.edit', $group_id)
            ->with('status', trans('entry.type.created'));
    }

    public function create(string $group_id)
    {
        $group = EntryGroup::find($group_id);

        if (!$group instanceof EntryGroup) {
            abort(404);
        }

        $allGroups = EntryGroup::ordered()->get();

        return $this->view('entries.groups.types.create', [
            'group' => $group,
            'groups' => $allGroups,
            'field_layouts' => FieldLayout::orderBy('name')->get(),
            'type_classes' => $this->discoverTypeClasses(),
        ]);
    }

    private function discoverTypeClasses(): Collection
    {
        return collect(glob(app_path('EntryTypes/*.php')))
            ->map(fn($path) => 'App\\EntryTypes\\' . basename($path, '.php'))
            ->filter(fn($class) => class_exists($class) && is_subclass_of($class, AbstractEntryType::class))
            ->values();
    }

    public function edit(string $group_id, string $type_id)
    {
        $group = EntryGroup::find($group_id);

        if (!$group instanceof EntryGroup) {
            abort(404);
        }

        $type = EntryTypeModel::with('fieldLayout')->find($type_id);

        if (!$type instanceof EntryTypeModel || $type->entry_group_id != $group_id) {
            abort(404);
        }

        $allGroups = EntryGroup::ordered()->get();

        return $this->view('entries.groups.types.edit', [
            'group' => $group,
            'groups' => $allGroups,
            'type' => $type,
            'field_layouts' => FieldLayout::orderBy('name')->get(),
            'type_classes' => $this->discoverTypeClasses(),
        ]);
    }

    public function update(EditEntryTypeRequest $request, string $group_id, string $type_id)
    {
        $type = EntryTypes::find((int)$type_id);

        if (!$type instanceof EntryTypeModel || $type->entry_group_id != $group_id) {
            abort(404);
        }

        EntryTypes::update($type, $request->validated());

        return redirect()
            ->route('entries.groups.edit', $group_id)
            ->with('success', trans('entry.type.updated'));
    }

    public function destroy(DeleteEntryTypeRequest $request, string $group_id, string $type_id)
    {
        $type = EntryTypes::find((int)$type_id);

        if (!$type instanceof EntryTypeModel || $type->entry_group_id != $group_id) {
            return redirect()
                ->route('entries.groups.edit', $group_id)
                ->with('failure', trans('entry.type.not_found'));
        }

        EntryTypes::delete($type);

        return redirect()
            ->route('entries.groups.edit', $group_id)
            ->with('success', trans('entry.type.deleted'));
    }

    public function confirm(string $group_id, string $type_id)
    {
        $group = EntryGroup::find($group_id);

        if (!$group instanceof EntryGroup) {
            abort(404);
        }

        $type = EntryTypes::find((int)$type_id);

        if (!$type instanceof EntryTypeModel || $type->entry_group_id != $group_id) {
            abort(404);
        }

        $allGroups = EntryGroup::ordered()->get();

        return $this->view('entries.groups.types.delete', [
            'group' => $group,
            'groups' => $allGroups,
            'type' => $type,
        ]);
    }
}
