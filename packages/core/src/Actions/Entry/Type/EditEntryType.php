<?php

namespace AdAstra\Actions\Entry\Type;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\EntryType;
use AdAstra\Services\EntryTypeService;

/**
 * @deprecated Delegate to EntryTypeService::update() directly.
 */
class EditEntryType extends AbstractAction
{
    public function __construct(private readonly EntryTypeService $service)
    {
    }

    public function edit(EntryType $type, array $input): EntryType
    {
        return $this->service->update($type, $input);
    }
}
