<?php
namespace App\Actions\Category\Group;

use App\Models\Category\Group;
use App\Actions\AbstractAction;

class CreateNewCategoryGroup extends AbstractAction
{
    public function create(array $input): Group
    {
        $cat_group = Group::create($input);
        $cat_group->field_groups()->detach();
        if (!empty($input['field_groups'])) {
            foreach ($input['field_groups'] as $field_group) {
                $cat_group->field_groups()->attach($field_group);
            }
        }

        return $cat_group;
    }
}
