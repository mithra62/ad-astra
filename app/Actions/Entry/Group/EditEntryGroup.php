<?php

namespace App\Actions\Entry\Group;

use App\Actions\AbstractAction;
use App\Models\EntryGroup;

class EditEntryGroup extends AbstractAction
{
    public function edit(EntryGroup $group, array $input): EntryGroup
    {
        $group->update([
            'name'            => $input['name'],
            'handle'          => $input['handle'],
            'description'     => $input['description'] ?? null,
            'sort_order'      => $input['sort_order'] ?? 0,
            'status_group_id' => $input['status_group_id'] ?? null,
            'field_layout_id' => $input['field_layout_id'] ?? null,
        ]);

        $group->categoryGroups()->sync($input['category_groups'] ?? []);
        $group->fieldGroups()->sync($input['field_groups'] ?? []);

        return $group->fresh();
    }
}
