<?php

namespace AdAstra\Actions\Field\Group;

use AdAstra\Models\Field\Group;

class EditFieldGroup
{
    public function edit(Group $group, array $input): bool
    {
        return $group->update($input);
    }
}
