<?php

namespace AdAstra\Http\Controllers\Admin;

use AdAstra\Actions\Status\CreateNewStatus;
use AdAstra\Actions\Status\EditStatus;
use AdAstra\Http\Requests\Status\DeleteStatusRequest;
use AdAstra\Http\Requests\Status\EditStatusRequest;
use AdAstra\Http\Requests\Status\StoreStatusRequest;
use AdAstra\Models\Status as StatusModel;
use AdAstra\Models\StatusGroup;

class Status extends Controller
{
    public function create(string $group_id)
    {
        $group = StatusGroup::find($group_id);
        if (!$group instanceof StatusGroup) {
            abort(404);
        }

        $groups = StatusGroup::ordered()->get();
        return $this->view('statuses.create', ['group' => $group, 'groups' => $groups]);
    }

    public function store(StoreStatusRequest $request)
    {
        $creator = app(CreateNewStatus::class);
        $data = $request->validated();
        $data['status_group_id'] = $request->group_id;
        $status = $creator->createByGroup($data);
        return redirect()->route('statuses.groups.show', $status->status_group_id)->with('success', trans('status.created'));
    }

    public function update(EditStatusRequest $request, string $id)
    {
        $status = StatusModel::find($id);
        if ($status instanceof StatusModel) {
            $editor = app(EditStatus::class);
            $editor->edit($status, $request->validated());
            return redirect()->route('statuses.groups.show', $status->status_group_id)->with('success', trans('status.updated'));
        }

        abort(404);
    }

    public function edit(string $id)
    {
        $status = StatusModel::with('group')->find($id);
        if (!$status instanceof StatusModel) {
            abort(404);
        }

        $groups = StatusGroup::ordered()->get();
        return $this->view('statuses.edit', ['status' => $status, 'groups' => $groups]);
    }

    public function destroy(DeleteStatusRequest $request, string $id)
    {
        $status = StatusModel::find($id);
        if ($status instanceof StatusModel) {
            $groupId = $status->status_group_id;
            $status->delete();
            return redirect()->route('statuses.groups.show', $groupId)->with('success', trans('status.deleted'));
        }

        abort(404);
    }

    public function confirm(string $id)
    {
        $status = StatusModel::with('group')->find($id);
        if (!$status instanceof StatusModel) {
            abort(404);
        }

        $groups = StatusGroup::ordered()->get();
        return $this->view('statuses.delete', ['status' => $status, 'groups' => $groups]);
    }
}
