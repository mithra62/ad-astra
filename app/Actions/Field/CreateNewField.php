<?php

namespace App\Actions\Field;

use App\Models\Category;
use App\Models\Field\Group;
use App\Models\Field;

class CreateNewField
{
    public function create(array $input): Group
    {
        return Field::create($input);
    }

    public function createByGroup(array $input): Field
    {
        $group = Group::find($input['group_id']);
        return $group->fields()->create($input);
    }
}
