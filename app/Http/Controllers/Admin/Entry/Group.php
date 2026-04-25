<?php

namespace App\Http\Controllers\Admin\Entry;

use App\Actions\Entry\Group\CreateNewEntryGroup;
use App\Actions\Entry\Group\EditEntryGroup;
use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\Entry\Group\DeleteEntryGroupRequest;
use App\Http\Requests\Entry\Group\EditEntryGroupRequest;
use App\Http\Requests\Entry\Group\StoreEntryGroupRequest;
use App\Models\Category\Group as CategoryGroup;
use App\Models\EntryGroup;
use App\Models\Field\Group as FieldGroup;
use App\Models\FieldLayout;
use App\Models\StatusGroup;

class Group extends Controller
{
    public function index()
    {
        $groups = EntryGroup::withCount(['entries', 'entryTypes'])
            ->with('statusGroup')
            ->ordered()
            ->paginate(20);
        return $this->view('entries.groups.index', ['groups' => $groups]);
    }

    public function create()
    {
        return $this->view('entries.groups.create', $this->formData());
    }

    public function store(StoreEntryGroupRequest $request)
    {
        $creator = app(CreateNewEntryGroup::class);
        $group   = $creator->create($request->validated());

        return redirect()
            ->route('entries.groups.show', $group->id)
            ->with('status', trans('entry.group.created'));
    }

    public function show(string $id)
    {
        $group = EntryGroup::with([
            'entryTypes',
            'statusGroup.statuses',
        ])->find($id);

        if (! $group instanceof EntryGroup) {
            abort(404);
        }

        $allGroups = EntryGroup::ordered()->get();
        $entries   = $group->entries()
            ->with(['entryType', 'creator', 'authors'])
            ->latest()
            ->paginate(20);

        return $this->view('entries.groups.view', [
            'group'   => $group,
            'groups'  => $allGroups,
            'entries' => $entries,
        ]);
    }

    public function edit(string $id)
    {
        $group = EntryGroup::with([
            'entryTypes.fieldLayout',
            'statusGroup',
            'categoryGroups',
            'fieldGroups',
            'fieldLayout',
        ])->find($id);

        if (! $group instanceof EntryGroup) {
            abort(404);
        }

        $allGroups = EntryGroup::ordered()->get();

        return $this->view('entries.groups.edit', array_merge(
            $this->formData(),
            [
                'group'  => $group,
                'groups' => $allGroups,
            ]
        ));
    }

    public function update(EditEntryGroupRequest $request, string $id)
    {
        $group = EntryGroup::find($id);
        if (! $group instanceof EntryGroup) {
            abort(404);
        }

        $editor = app(EditEntryGroup::class);
        $editor->edit($group, $request->validated());

        return redirect()
            ->route('entries.groups.edit', $id)
            ->with('success', trans('entry.group.updated'));
    }

    public function destroy(DeleteEntryGroupRequest $request, string $id)
    {
        $group = EntryGroup::find($id);
        if ($group instanceof EntryGroup) {
            $group->delete();
            return redirect()
                ->route('entries.groups')
                ->with('success', trans('entry.group.deleted'));
        }

        return redirect()
            ->route('entries.groups')
            ->with('failure', trans('entry.group.not_found'));
    }

    public function confirm(string $id)
    {
        $group = EntryGroup::withCount('entries')->find($id);
        if (! $group instanceof EntryGroup) {
            return redirect()->route('entries.groups')->with('failure', trans('entry.group.not_found'));
        }

        $allGroups = EntryGroup::ordered()->get();

        return $this->view('entries.groups.delete', [
            'group'  => $group,
            'groups' => $allGroups,
        ]);
    }

    private function formData(): array
    {
        return [
            'status_groups'   => StatusGroup::ordered()->get(),
            'category_groups' => CategoryGroup::orderBy('name')->get(),
            'field_groups'    => FieldGroup::orderBy('name')->get(),
            'field_layouts'   => FieldLayout::orderBy('name')->get(),
        ];
    }
}
