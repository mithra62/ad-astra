<?php

namespace App\Http\Controllers\Admin\Status;

use App\Actions\Status\Group\CreateNewStatusGroup;
use App\Actions\Status\Group\EditStatusGroup;
use App\Http\Controllers\Admin\Controller;
use App\Http\Requests\Status\Group\DeleteStatusGroupRequest;
use App\Http\Requests\Status\Group\EditStatusGroupRequest;
use App\Http\Requests\Status\Group\StoreStatusGroupRequest;
use App\Models\StatusGroup;

class Group extends Controller
{
    public function index()
    {
        $groups = StatusGroup::withCount('statuses')->withCount('entryGroups')->ordered()->paginate(20);
        return $this->view('statuses.groups.index', ['groups' => $groups]);
    }

    public function store(StoreStatusGroupRequest $request)
    {
        $creator = app(CreateNewStatusGroup::class);
        $group = $creator->create($request->validated());
        return redirect()->route('statuses.groups.show', $group->id)->with('success', trans('status.group.created'));
    }

    public function create()
    {
        return $this->view('statuses.groups.create');
    }

    public function show(string $id)
    {
        $group = StatusGroup::find($id);
        if (!$group instanceof StatusGroup) {
            abort(404);
        }

        $groups = StatusGroup::with('entryGroups')->ordered()->get();
        $statuses = $group->statuses()->orderBy('sort_order')->get();

        return $this->view('statuses.groups.view', [
            'group' => $group,
            'groups' => $groups,
            'statuses' => $statuses,
        ]);
    }

    public function update(EditStatusGroupRequest $request, string $id)
    {
        $group = StatusGroup::find($id);
        if ($group instanceof StatusGroup) {
            $editor = app(EditStatusGroup::class);
            $editor->edit($group, $request->validated());
            return redirect()->route('statuses.groups')->with('success', trans('status.group.updated'));
        }

        abort(404);
    }

    public function edit(string $id)
    {
        $group = StatusGroup::find($id);
        if (!$group instanceof StatusGroup) {
            abort(404);
        }

        $groups = StatusGroup::ordered()->get();
        return $this->view('statuses.groups.edit', ['group' => $group, 'groups' => $groups]);
    }

    public function destroy(DeleteStatusGroupRequest $request, string $id)
    {
        $group = StatusGroup::find($id);
        if ($group instanceof StatusGroup) {
            $group->delete();
            return redirect()->route('statuses.groups')->with('success', trans('status.group.deleted'));
        }

        return redirect()->route('statuses.groups')->with('failure', trans('status.group.not_found'));
    }

    public function confirm(string $id)
    {
        $group = StatusGroup::find($id);
        if (!$group instanceof StatusGroup) {
            return redirect()->route('statuses.groups')->with('failure', trans('status.group.not_found'));
        }

        $groups = StatusGroup::with('entryGroups')->ordered()->get();
        return $this->view('statuses.groups.delete', ['groups' => $groups, 'group' => $group]);
    }
}
