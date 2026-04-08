<?php
namespace App\Actions\Category;

use App\Models\Category;
use App\Models\Category\Group;
use App\Actions\AbstractAction;

class CreateNewCategory extends AbstractAction
{
    public function create(array $input): Category
    {
        $cat = Category::create($input);

        return $cat;
    }

    public function createByGroup(array $input): Category
    {
        $group = Group::find($input['group_id']);
        return $group->categories()->create($input);
    }
}
