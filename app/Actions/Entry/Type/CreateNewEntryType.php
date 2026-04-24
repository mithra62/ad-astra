<?php

namespace App\Actions\Entry\Type;

use App\Actions\AbstractAction;
use App\Models\EntryType;

class CreateNewEntryType extends AbstractAction
{
    public function create(string $groupId, array $input): EntryType
    {
        return EntryType::create([
            'entry_group_id'  => $groupId,
            'name'            => $input['name'],
            'handle'          => $input['handle'],
            'class'           => $input['class'],
            'sort_order'      => $input['sort_order'] ?? 0,
            'field_layout_id' => $input['field_layout_id'] ?? null,
        ]);
    }
}
