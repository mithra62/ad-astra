<?php

namespace App\Actions\Status;

use App\Actions\AbstractAction;
use App\Models\Status;
use App\Models\StatusGroup;

class CreateNewStatus extends AbstractAction
{
    public function create(array $input): Status
    {
        return Status::create($input);
    }

    public function createByGroup(array $input): Status
    {
        $group = StatusGroup::find($input['status_group_id']);
        return $group->statuses()->create($input);
    }
}
