<?php

namespace App\Actions\Category\Group;

use App\Actions\AbstractAction;
use App\Models\Category\Group;
use App\Models\FieldLayout;

class CreateNewCategoryGroup extends AbstractAction
{
    public function create(array $input): Group
    {
        $layout = FieldLayout::create(['name' => $input['name'] . ' Layout cat', 'handle' => $input['handle'] . '-layout-cat']);
        $input['field_layout_id'] = $layout->id;
        $cat_group = Group::create($input);
        if (!empty($input['field_groups'])) {
            $cat_group->fieldGroups()->sync($input['field_groups']);
        }

        return $cat_group;
    }
}
