<?php

namespace AdAstra\Actions\Status\Group;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\StatusGroup;

class EditStatusGroup extends AbstractAction
{
    public function edit(StatusGroup $group, array $input): bool
    {
        return $group->update($input);
    }
}
