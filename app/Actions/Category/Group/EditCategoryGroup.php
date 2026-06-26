<?php

namespace App\Actions\Category\Group;

use App\Actions\AbstractAction;
use App\Models\Category\Group;

class EditCategoryGroup extends AbstractAction
{
    /**
     * Edit a specific group record in the database.
     *
     * @param Group $group The group to be edited
     * @param array $input The new data to update the group with
     * @return bool True if the update was successful, false otherwise
     */
    public function edit(Group $group, array $input): bool
    {
        return $group->update($input);
    }
}
