<?php

namespace App\Actions\Entry\Group;

use App\Actions\AbstractAction;
use App\Models\EntryGroup;
use App\Models\FieldLayout;

class CreateNewEntryGroup extends AbstractAction
{
    public function create(array $input): EntryGroup
    {
        $layout = FieldLayout::create(['name' => $input['name'] . ' Entries']);
        $group = EntryGroup::create([
            'name' => $input['name'],
            'handle' => $input['handle'],
            'description' => $input['description'] ?? null,
            'sort_order' => $input['sort_order'] ?? 0,
            'status_group_id' => $input['status_group_id'] ?? null,
            'field_layout_id' => $layout->id,
        ]);

        $group->categoryGroups()->sync($input['category_groups'] ?? []);
        $group->fieldGroups()->sync($input['field_groups'] ?? []);

        //now create the Entry Type @todo

        return $group;
    }
}
