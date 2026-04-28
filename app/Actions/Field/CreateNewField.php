<?php

namespace App\Actions\Field;


use App\Models\Field;
use App\Models\Field\Group;

class CreateNewField
{
    public function createByGroup(array $input): Field
    {
        $group = Group::find($input['group_id']);
        return $group->fields()->create($input);
    }

    public function create(array $input): Field
    {
        return Field::create($input);
    }
}
