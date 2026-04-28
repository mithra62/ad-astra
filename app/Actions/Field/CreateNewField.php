<?php

namespace App\Actions\Field;


use App\Models\Field\Group;
use App\Models\Field;

class CreateNewField
{
    public function create(array $input): Field
    {
        return Field::create($input);
    }

    public function createByGroup(array $input): Field
    {
        $group = Group::find($input['group_id']);
        return $group->fields()->create($input);
    }
}
