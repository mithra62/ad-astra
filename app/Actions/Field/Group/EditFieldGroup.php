<?php

namespace App\Actions\Field\Group;

use App\Models\Field\Group;

class EditFieldGroup
{
    public function edit(Group $group, array $input): bool
    {
        return $group->update($input);
    }
}
