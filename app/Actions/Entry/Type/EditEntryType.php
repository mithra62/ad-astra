<?php

namespace App\Actions\Entry\Type;

use App\Actions\AbstractAction;
use App\Models\EntryType;
use App\Services\EntryTypeService;

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
