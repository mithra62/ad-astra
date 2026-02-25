<?php

namespace App\Actions\Field\Group;

use App\Models\Field\Group;

class CreateNewFieldGroup
{
    public function create(array $input): Group
    {
        return Group::create($input);
    }
}
