<?php

namespace AdAstra\Actions\Entry\Group;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\EntryGroup;
use AdAstra\Services\EntryGroupService;

/**
 * @deprecated Delegate to EntryGroupService::update() directly.
 */
class EditEntryGroup extends AbstractAction
{
    public function __construct(private readonly EntryGroupService $service)
    {
    }

    public function edit(EntryGroup $group, array $input): EntryGroup
    {
        return $this->service->update($group, $input);
    }
}
