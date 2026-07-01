<?php

namespace AdAstra\Actions\Status\Group;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\StatusGroup;

class CreateNewStatusGroup extends AbstractAction
{
    public function create(array $input): StatusGroup
    {
        return StatusGroup::create($input);
    }
}
