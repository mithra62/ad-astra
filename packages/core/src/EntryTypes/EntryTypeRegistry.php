<?php

namespace AdAstra\EntryTypes;

use AdAstra\Models\EntryType as EntryTypeRecord;

class EntryTypeRegistry
{
    /** @var array<string, AbstractEntryType> */
    private array $handleCache = [];

    /** @var array<int, AbstractEntryType> */
    private array $idCache = [];

    public function resolveByHandle(string $handle): AbstractEntryType
    {
        if (!isset($this->handleCache[$handle])) {
            $record = EntryTypeRecord::where('handle', $handle)
                ->with(['entryGroup', 'entryBehavior', 'fieldLayout.tabs.elements.field.fieldType'])
                ->firstOrFail();

            $instance = $this->instantiate($record);
            $this->handleCache[$handle] = $instance;
            $this->idCache[$record->getKey()] = $instance;
        }

        return $this->handleCache[$handle];
    }

    public function resolveByRecord(EntryTypeRecord $record): AbstractEntryType
    {
        $handle = $record->handle;

        if (!isset($this->handleCache[$handle])) {
            $instance = $this->instantiate($record);
            $this->handleCache[$handle] = $instance;
            $this->idCache[$record->getKey()] = $instance;
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
}
