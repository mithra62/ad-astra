<?php

namespace App\Actions\Entry\Type;

use App\Actions\AbstractAction;
use App\Models\EntryType;
use App\Services\EntryTypeService;

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
        return $this->service->create((int)$groupId, $input);
    }
}
