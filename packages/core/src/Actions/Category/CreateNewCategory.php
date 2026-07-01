<?php

namespace AdAstra\Actions\Category;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\Category;
use AdAstra\Models\Category\Group;
use AdAstra\Repositories\CategoryRepository;

class CreateNewCategory extends AbstractAction
{
    public function create(array $input): Category
    {
        $group = Group::findOrFail($input['group_id']);

        return app(CategoryRepository::class)->create($group, $input);
    }
}
