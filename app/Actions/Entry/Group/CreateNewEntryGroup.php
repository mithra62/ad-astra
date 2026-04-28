<?php

namespace App\Actions\Entry\Group;

use App\Actions\AbstractAction;
use App\Models\EntryGroup;
use App\Services\EntryGroupService;

/**
 * @deprecated Delegate to EntryGroupService::create() directly.
 */
class CreateNewEntryGroup extends AbstractAction
{
    public function __construct(private readonly EntryGroupService $service) {}

    public function create(array $input): EntryGroup
    {
        return $this->service->create($input);
    }
}
