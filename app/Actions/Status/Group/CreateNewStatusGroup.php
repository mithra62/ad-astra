<?php

namespace App\Actions\Status\Group;

use App\Actions\AbstractAction;
use App\Models\StatusGroup;

class CreateNewStatusGroup extends AbstractAction
{
    public function create(array $input): StatusGroup
    {
        return StatusGroup::create($input);
    }
}
