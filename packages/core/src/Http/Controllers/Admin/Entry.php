<?php

namespace AdAstra\Http\Controllers\Admin;

use AdAstra\Actions\Entry\CreateNewEntry;
use AdAstra\Actions\Entry\UpdateEntry;
use AdAstra\Facades\Entries;
use AdAstra\Facades\EntryAuthors;
use AdAstra\Http\Requests\Entry\DeleteEntryRequest;
use AdAstra\Http\Requests\Entry\EditEntryRequest;
use AdAstra\Http\Requests\Entry\StoreEntryRequest;
use AdAstra\Models\EntryGroup;
use Illuminate\Http\Request;

class Entry extends Controller
{
    public function store(StoreEntryRequest $request)
    {
        $creator = app(CreateNewEntry::class);
        $entry = $creator->create(array_merge($request->validated(),
                ['entry_group_id' => $request->route()->parameter('group_id')])
        );

        return redirect()
            ->route('entries.groups.show', $entry->entry_group_id)
            ->with('success', trans('entry.created'));
    }

    public function create(string $group_id, Request $request)
    {
        $group = EntryGroup::with([
            'entryTypes.fieldLayout.tabs' => fn($q) => $q->orderBy('sort_order'),
            'entryTypes.fieldLayout.tabs.elements' => fn($q) => $q->orderBy('sort_order'),
            'entryTypes.fieldLayout.tabs.elements.field.fieldType',
            'fieldLayout.tabs' => fn($q) => $q->orderBy('sort_order'),
            'fieldLayout.tabs.elements' => fn($q) => $q->orderBy('sort_order'),
            'fieldLayout.tabs.elements.field.fieldType',
            'statusGroup.statuses',
            'categoryGroups.categories',
        ])->find($group_id);

        if (!$group instanceof EntryGroup) {
            abort(404);
        }

        if ($group->entryTypes->isEmpty()) {
            return redirect()->route('entries.groups.show', $group)
                ->with('failure', 'This entry group has no entry types configured.');
        }

        $typeHandle = $request->query('type');
        $entryType = $typeHandle
            ? $group->entryTypes->firstWhere('handle', $typeHandle)
            : $group->entryTypes->first();

        if (!$entryType) {
            abort(404);
        }

        $allGroups = EntryGroup::withCount('entries')->ordered()->get();
        $authors = EntryAuthors::getEligible();

        return $this->view('entries.create', [
            'group' => $group,
            'groups' => $allGroups,
            'entryType' => $entryType,
            'authors' => $authors,
        ]);
    }

    public function edit(string $id)
    {
        $entry = Entries::get((int)$id);

        if (!$entry) {
            abort(404);
        }

        $entry->loadMissing([
            'entryGroup.entryTypes',
            'entryTree',
            'entryGroup.statusGroup.statuses',
            'entryGroup.categoryGroups.categories',
            'entryGroup.fieldLayout.tabs' => fn($q) => $q->orderBy('sort_order'),
            'entryGroup.fieldLayout.tabs.elements' => fn($q) => $q->orderBy('sort_order'),
            'entryGroup.fieldLayout.tabs.elements.field.fieldType',
            'entryType.fieldLayout.tabs' => fn($q) => $q->orderBy('sort_order'),
            'entryType.fieldLayout.tabs.elements' => fn($q) => $q->orderBy('sort_order'),
            'entryType.fieldLayout.tabs.elements.field.fieldType',
        ]);

        $allGroups = EntryGroup::withCount('entries')->ordered()->get();
        $authors = EntryAuthors::getEligible();

        return $this->view('entries.edit', [
            'entry' => $entry,
            'groups' => $allGroups,
            'authors' => $authors,
            'field_values' => $entry->fieldArray(),
        ]);
    }

    public function update(EditEntryRequest $request, string $id)
    {
        $entry = Entries::findMeta((int)$id);

        if (!$entry) {
            abort(404);
        }

        $entry->loadMissing('entryGroup.statusGroup.statuses');

        $editor = app(UpdateEntry::class);
        $entry = $editor->update($entry, $request->validated());

        return redirect()
            ->route('entries.edit', $entry)
            ->with('success', trans('entry.updated'));
    }

    public function destroy(DeleteEntryRequest $request, string $id)
    {
        $entry = Entries::find((int)$id);

        if (!$entry) {
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
        $entry = Entries::findMeta((int)$id);

        if (!$entry) {
            abort(404);
        }

        $allGroups = EntryGroup::withCount('entries')->ordered()->get();

        return $this->view('entries.delete', [
            'entry' => $entry,
            'groups' => $allGroups,
        ]);
    }
}
