<?php

namespace AdAstra\Actions\Status;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use Illuminate\Support\Facades\DB;

class CreateNewStatus extends AbstractAction
{
    public function createByGroup(array $input): Status
    {
        return DB::transaction(function () use ($input) {
            // Lock the status group row so concurrent default-setting requests
            // queue here rather than racing through the clear-and-create sequence.
            $group = StatusGroup::lockForUpdate()->findOrFail($input['status_group_id']);

            if (!empty($input['is_default'])) {
                $group->statuses()->where('is_default', true)->update(['is_default' => false]);
            }

            return $group->statuses()->create($input);
        });
    }

    public function create(array $input): Status
    {
        return DB::transaction(function () use ($input) {
            // Lock the status group row to serialise concurrent default changes.
            StatusGroup::lockForUpdate()->findOrFail($input['status_group_id']);

            if (!empty($input['is_default'])) {
                Status::where('status_group_id', $input['status_group_id'])
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return Status::create($input);
        });
    }
}
