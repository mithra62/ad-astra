<?php

namespace App\Actions\Category\Group;

use App\Actions\AbstractAction;
use App\Models\Category\Group;
class CreateNewCategoryGroup extends AbstractAction
{
    public function create(array $input): Group
    {
        return Group::create($input);
    }
}
