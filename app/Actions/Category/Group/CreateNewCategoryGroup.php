<?php
namespace App\Actions\Category\Group;

use App\Models\Category\Group;

class CreateNewCategoryGroup
{
    public function create(array $input): Group
    {
        $cat_group = Group::create($input);

        return $cat_group;
    }
}
