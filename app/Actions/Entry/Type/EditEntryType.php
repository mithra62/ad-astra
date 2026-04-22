<?php

namespace App\Actions\Entry\Type;

use App\Actions\AbstractAction;
use App\Models\EntryType;

class EditEntryType extends AbstractAction
{
    public function edit(EntryType $type, array $input): EntryType
    {
        $type->update([
            'name'            => $input['name'],
            'handle'          => $input['handle'],
            'class'           => $input['class'],
            'sort_order'      => $input['sort_order'] ?? 0,
            'field_layout_id' => $input['field_layout_id'] ?? null,
        ]);

        return $type->fresh();
    }
}
