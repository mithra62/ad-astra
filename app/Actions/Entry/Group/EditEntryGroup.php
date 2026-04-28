<?php

namespace App\Actions\Entry\Group;

use App\Actions\AbstractAction;
use App\Models\EntryGroup;
use App\Services\EntryGroupService;

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
