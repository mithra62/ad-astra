<?php
namespace App\Actions\Category\Group;

use App\Models\Category\Group;
use App\Actions\AbstractAction;

class CreateNewCategoryGroup extends AbstractAction
{
    public function create(array $input): Group
    {
        $cat_group = Group::create($input);

        return $cat_group;
    }
}
