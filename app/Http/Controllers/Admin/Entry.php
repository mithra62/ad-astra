<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Entry\CreateNewEntry;
use App\Actions\Entry\UpdateEntry;
use App\Facades\Entries;
use App\Http\Requests\Entry\DeleteEntryRequest;
use App\Http\Requests\Entry\EditEntryRequest;
use App\Http\Requests\Entry\StoreEntryRequest;
use App\Models\Entry as EntryModel;
use App\Models\EntryGroup;
use App\Models\User;
use Illuminate\Http\Request;

class Entry extends Controller
{
    public function create(string $group_id, Request $request)
    {
        $group = EntryGroup::with([
            'entryTypes.fieldLayout.tabs.elements.field.fieldType',
            'fieldLayout.tabs.elements.field.fieldType',
            'statusGroup.statuses',
            'categoryGroups.categories',
        ])->find($group_id);

        if (! $group instanceof EntryGroup) {
            abort(404);
        }

        if ($group->entryTypes->isEmpty()) {
            return redirect()->route('entries.groups.show', $group)
                ->with('failure', 'This entry group has no entry types configured.');
        }

        $typeHandle = $request->query('type');
        $entryType  = $typeHandle
            ? $group->entryTypes->firstWhere('handle', $typeHandle)
            : $group->entryTypes->first();

        if (! $entryType) {
            abort(404);
        }

        $allGroups = EntryGroup::ordered()->get();
        $users     = User::orderBy('name')->get();

        return $this->view('entries.create', [
            'group'     => $group,
            'groups'    => $allGroups,
            'entryType' => $entryType,
            'users'     => $users,
        ]);
    }

    public function store(StoreEntryRequest $request)
    {
        $creator = app(CreateNewEntry::class);
        $entry   = $creator->create($request->validated());

        return redirect()
            ->route('entries.groups.show', $entry->entry_group_id)
            ->with('status', trans('entry.created'));
    }

    public function edit(string $id)
    {
        $entry = EntryModel::with([
            'entryGroup.entryTypes',
            'entryGroup.statusGroup.statuses',
            'entryGroup.categoryGroups.categories',
            'entryGroup.fieldLayout.tabs.elements.field.fieldType',
            'entryType.fieldLayout.tabs.elements.field.fieldType',
            'authors',
            'categories',
            'fieldValues.field.fieldType',
        ])->find($id);

        if (! $entry instanceof EntryModel) {
            abort(404);
        }

        $allGroups = EntryGroup::ordered()->get();
        $users     = User::orderBy('name')->get();

        return $this->view('entries.edit', [
            'entry'       => $entry,
            'groups'      => $allGroups,
            'users'       => $users,
            'field_values' => $entry->fieldArray(),
        ]);
    }

    public function update(EditEntryRequest $request, string $id)
    {
        $entry = EntryModel::with([
            'entryGroup.statusGroup.statuses',
            'entryType',
        ])->find($id);

        if (! $entry instanceof EntryModel) {
            abort(404);
        }

        $editor = app(UpdateEntry::class);
        $entry  = $editor->update($entry, $request->validated());

        return redirect()
            ->route('entries.edit', $entry)
            ->with('success', trans('entry.updated'));
    }

    public function destroy(DeleteEntryRequest $request, string $id)
    {
        $entry = EntryModel::find($id);
        if (! $entry instanceof EntryModel) {
            abort(404);
        }

        $groupId = $entry->entry_group_id;
        Entries::delete($entry);

        return redirect()
            ->route('entries.groups.show', $groupId)
            ->with('success', trans('entry.deleted'));
    }

    public function confirm(string $id)
    {
        $entry = EntryModel::with([
            'entryGroup',
            'entryType',
        ])->find($id);

        if (! $entry instanceof EntryModel) {
            abort(404);
        }

        $allGroups = EntryGroup::ordered()->get();

        return $this->view('entries.delete', [
            'entry'  => $entry,
            'groups' => $allGroups,
        ]);
    }
}
