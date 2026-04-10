<?php
namespace App\Actions\Category\Group;

use App\Models\Category\Group;
use App\Actions\AbstractAction;

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
        $group->field_groups()->detach();
        if (!empty($input['field_groups'])) {
            foreach ($input['field_groups'] as $field_group) {
                $group->field_groups()->attach($field_group);
            }
        }

        return $group->update($input);
    }
}
