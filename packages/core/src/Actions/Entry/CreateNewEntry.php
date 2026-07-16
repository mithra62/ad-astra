<?php

namespace AdAstra\Actions\Entry;

use AdAstra\Actions\AbstractAction;
use AdAstra\Facades\Content;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryType;

class CreateNewEntry extends AbstractAction
{
    public function create(array $input): Entry
    {
        $groupId = $input['entry_group_id'] ?? request()->route()?->parameter('group_id');
        $typeRecord = EntryType::where('handle', $input['type_handle'])
            ->where('entry_group_id', $groupId)
            ->firstOrFail();

        return Content::create($typeRecord->handle, $input);
    }
}
