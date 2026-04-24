<?php
namespace App\Actions\Category\Group;

use App\Models\Category\Group;
use App\Actions\AbstractAction;

class CreateNewCategoryGroup extends AbstractAction
{
    public function create(array $input): Group
    {
        $cat_group = Group::create($input);
        if (!empty($input['field_groups'])) {
            $cat_group->fieldGroups()->sync($input['field_groups']);
        }

        return $cat_group;
    }
}
