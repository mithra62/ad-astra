<?php

namespace AdAstra\EntryTypes;

use AdAstra\Models\EntryType as EntryTypeRecord;

class EntryTypeRegistry
{
    /** @var array<string, AbstractEntryType> */
    private array $handleCache = [];

    public function resolveByHandle(string $handle): AbstractEntryType
    {
        if (!isset($this->handleCache[$handle])) {
            $record = EntryTypeRecord::where('handle', $handle)
                ->with(['entryGroup', 'entryBehavior', 'fieldLayout.tabs.elements.field.fieldType'])
                ->firstOrFail();

            $this->handleCache[$handle] = $this->instantiate($record);
        }

        return $this->handleCache[$handle];
    }

    private function instantiate(EntryTypeRecord $record): AbstractEntryType
    {
        $behavior = $record->entryBehavior;

        if ($behavior === null) {
            return new GeneralEntryType($record);
        }

        return $behavior->instance($record);
    }

    public function resolveByRecord(EntryTypeRecord $record): AbstractEntryType
    {
        $handle = $record->handle;

        if (!isset($this->handleCache[$handle])) {
            $this->handleCache[$handle] = $this->instantiate($record);
        }

        return $this->handleCache[$handle];
    }
}
