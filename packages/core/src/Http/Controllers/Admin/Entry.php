<?php

namespace AdAstra\Http\Controllers\Admin;

use AdAstra\Actions\Entry\CreateNewEntry;
use AdAstra\Actions\Entry\UpdateEntry;
use AdAstra\Facades\Entries;
use AdAstra\Facades\EntryAuthors;
use AdAstra\Http\Requests\Entry\DeleteEntryRequest;
use AdAstra\Http\Requests\Entry\EditEntryRequest;
use AdAstra\Http\Requests\Entry\StoreEntryRequest;
use AdAstra\Models\Entry as EntryModel;
use AdAstra\Models\EntryGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Entry extends Controller
{
    public function store(StoreEntryRequest $request)
    {
        $creator = app(CreateNewEntry::class);
        $entry = $creator->create(
            array_merge(
                $request->validated(),
                ['entry_group_id' => $request->route()->parameter('group_id')]
            )
        );

        return redirect()
            ->route('entries.groups.show', $entry->entry_group_id)
            ->with('success', trans('entry.created'));
    }

    public function create(string $group_id, Request $request)
    {
        $group = EntryGroup::with([
            'entryTypes.fieldLayout.tabs' => fn ($q) => $q->orderBy('sort_order'),
            'entryTypes.fieldLayout.tabs.elements' => fn ($q) => $q->orderBy('sort_order'),
            'entryTypes.fieldLayout.tabs.elements.field.fieldType',
            'fieldLayout.tabs' => fn ($q) => $q->orderBy('sort_order'),
            'fieldLayout.tabs.elements' => fn ($q) => $q->orderBy('sort_order'),
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
            'parent_entry' => $this->parentEntryPrefill(),
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
            'entryTree.parent.entry',
            'entryGroup.statusGroup.statuses',
            'entryGroup.categoryGroups.categories',
            'entryGroup.fieldLayout.tabs' => fn ($q) => $q->orderBy('sort_order'),
            'entryGroup.fieldLayout.tabs.elements' => fn ($q) => $q->orderBy('sort_order'),
            'entryGroup.fieldLayout.tabs.elements.field.fieldType',
            'entryType.fieldLayout.tabs' => fn ($q) => $q->orderBy('sort_order'),
            'entryType.fieldLayout.tabs.elements' => fn ($q) => $q->orderBy('sort_order'),
            'entryType.fieldLayout.tabs.elements.field.fieldType',
        ]);

        $allGroups = EntryGroup::withCount('entries')->ordered()->get();
        $authors = EntryAuthors::getEligible();

        return $this->view('entries.edit', [
            'entry' => $entry,
            'groups' => $allGroups,
            'authors' => $authors,
            'field_values' => $entry->fieldArray(),
            'parent_entry' => $this->parentEntryPrefill($entry),
        ]);
    }

    /**
     * Resolve the {id, title, uri} prefill for the Hierarchy tab's parent
     * picker. Flashed old input (a validation-error redirect) wins over the
     * persisted tree parent so the user's last choice survives the round trip.
     *
     * @return array{id: int, title: string, uri: string|null}|null
     */
    private function parentEntryPrefill(?EntryModel $entry = null): ?array
    {
        $oldParentId = session()->getOldInput('parent_entry_id');

        $parent = $oldParentId !== null && $oldParentId !== ''
            ? EntryModel::query()->find((int)$oldParentId)
            : $entry?->entryTree?->parent?->entry;

        if (!$parent instanceof EntryModel) {
            return null;
        }

        $parent->loadMissing('entryTree:id,entry_id,uri');

        return [
            'id' => $parent->id,
            'title' => $parent->title,
            'uri' => $parent->entryTree?->uri,
        ];
    }

    /**
     * Paginated/filterable JSON listing of candidate parent Entries for the
     * entry Hierarchy tab's ajax parent picker.
     *
     * Only entries that already have an entry_trees row qualify — the
     * parent_entry_id request rule requires exists:entry_trees,entry_id.
     * Results are scoped to a single entry group and optionally exclude the
     * entry being edited. Page size comes from the request's per_page.
     */
    public function entry_picker(Request $request): JsonResponse
    {
        $request->validate([
            'entry_group_id' => ['required', 'integer'],
            'exclude' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $q = trim((string)$request->input('q', ''));

        $query = EntryModel::query()
            ->with('entryTree:id,entry_id,uri')
            ->where('entry_group_id', (int)$request->input('entry_group_id'))
            ->has('entryTree')
            ->orderBy('title');

        if ($request->filled('exclude')) {
            $query->whereKeyNot((int)$request->input('exclude'));
        }

        if ($q !== '') {
            // Escape SQL LIKE wildcards in user input, then declare the escape
            // character explicitly so the protection works across drivers.
            // The escape character must not be a backslash: inside a MySQL
            // string literal, '\' escapes the closing quote and produces a
            // syntax error, while SQLite treats it literally.
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            $query->whereRaw("title LIKE ? ESCAPE '!'", [$like]);
        }

        $paginator = $query->paginate($request->integer('per_page') ?: null);

        $data = $paginator->getCollection()->map(function (EntryModel $entry): array {
            return [
                'id' => $entry->id,
                'title' => $entry->title,
                'uri' => $entry->entryTree?->uri,
            ];
        })->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
            ],
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
