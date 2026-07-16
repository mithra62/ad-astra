<?php

namespace AdAstra\Actions\Field\Group;

use AdAstra\Models\Field\Group;

class CreateNewFieldGroup
{
    public function create(array $input): Group
    {
        return Group::create($input);
    }
}
