<?php

namespace App\Actions\Status\Group;

use App\Actions\AbstractAction;
use App\Models\StatusGroup;

class EditStatusGroup extends AbstractAction
{
    public function edit(StatusGroup $group, array $input): bool
    {
        return $group->update($input);
    }
}
