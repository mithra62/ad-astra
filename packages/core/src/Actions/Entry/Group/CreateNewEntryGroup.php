<?php

namespace AdAstra\Actions\Entry\Group;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\EntryGroup;
use AdAstra\Services\EntryGroupService;

/**
 * @deprecated Delegate to EntryGroupService::create() directly.
 */
class CreateNewEntryGroup extends AbstractAction
{
    public function __construct(private readonly EntryGroupService $service)
    {
    }

    public function create(array $input): EntryGroup
    {
        return $this->service->create($input);
    }
}
