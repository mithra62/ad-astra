<?php

namespace AdAstra\Actions\Entry\Type;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\EntryType;
use AdAstra\Services\EntryTypeService;

/**
 * @deprecated Delegate to EntryTypeService::create() directly.
 */
class CreateNewEntryType extends AbstractAction
{
    public function __construct(private readonly EntryTypeService $service)
    {
    }

    public function create(string|int $groupId, array $input): EntryType
    {
        return $this->service->create(['entry_group_id' => (int)$groupId] + $input);
    }
}
