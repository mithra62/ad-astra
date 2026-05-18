<?php

namespace App\Actions\Category;

use App\Actions\AbstractAction;
use App\Models\Category;
use App\Models\Category\Group;
use App\Repositories\CategoryRepository;

class CreateNewCategory extends AbstractAction
{
    public function create(array $input): Category
    {
        $group = Group::findOrFail($input['group_id']);

        return app(CategoryRepository::class)->create($group, $input);
    }
}
