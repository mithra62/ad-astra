<?php

namespace AdAstra\Actions\Category\Group;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\Category\Group;
class CreateNewCategoryGroup extends AbstractAction
{
    public function create(array $input): Group
    {
        return Group::create($input);
    }
}
