<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Status\CreateNewStatus;
use App\Actions\Status\EditStatus;
use App\Http\Requests\Status\DeleteStatusRequest;
use App\Http\Requests\Status\EditStatusRequest;
use App\Http\Requests\Status\StoreStatusRequest;
use App\Models\Status as StatusModel;
use App\Models\StatusGroup;

class Status extends Controller
{
    public function create(string $group_id)
    {
        $group = StatusGroup::find($group_id);
        if (! $group instanceof StatusGroup) {
            abort(404);
        }

        $groups = StatusGroup::ordered()->get();
        return $this->view('statuses.create', ['group' => $group, 'groups' => $groups]);
    }

    public function store(StoreStatusRequest $request)
    {
        $creator = app(CreateNewStatus::class);
        $status  = $creator->createByGroup($request->all());
        return redirect()->route('statuses.groups.show', $status->status_group_id)->with('status', trans('status.created'));
    }

    public function edit(string $id)
    {
        $status = StatusModel::with('group')->find($id);
        if (! $status instanceof StatusModel) {
            abort(404);
        }

        $groups = StatusGroup::ordered()->get();
        return $this->view('statuses.edit', ['status' => $status, 'groups' => $groups]);
    }

    public function update(EditStatusRequest $request, string $id)
    {
        $status = StatusModel::find($id);
        if ($status instanceof StatusModel) {
            $editor = app(EditStatus::class);
            $editor->edit($status, $request->all());
            return redirect()->route('statuses.groups.show', $status->status_group_id)->with('success', trans('status.updated'));
        }

        abort(404);
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
        if (! $status instanceof StatusModel) {
            abort(404);
        }

        $groups = StatusGroup::ordered()->get();
        return $this->view('statuses.delete', ['status' => $status, 'groups' => $groups]);
    }
}
