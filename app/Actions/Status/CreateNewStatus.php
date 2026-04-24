<?php

namespace App\Actions\Status;

use App\Actions\AbstractAction;
use App\Models\Status;
use App\Models\StatusGroup;

class CreateNewStatus extends AbstractAction
{
    public function create(array $input): Status
    {
        if (! empty($input['is_default'])) {
            Status::where('status_group_id', $input['status_group_id'])
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        return Status::create($input);
    }

    public function createByGroup(array $input): Status
    {
        $group = StatusGroup::find($input['status_group_id']);

        if (! empty($input['is_default'])) {
            $group->statuses()->where('is_default', true)->update(['is_default' => false]);
        }

        return $group->statuses()->create($input);
    }
}
