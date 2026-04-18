<?php

namespace App\EntryTypes;

use App\Models\EntryType as EntryTypeRecord;
use RuntimeException;

class EntryTypeRegistry
{
    public function resolveByHandle(string $handle): AbstractEntryType
    {
        $record = EntryTypeRecord::where('handle', $handle)
            ->with(['entryGroup', 'fieldLayout.tabs.elements.field.fieldType'])
            ->firstOrFail();

        return $this->instantiate($record);
    }

    public function resolveByRecord(EntryTypeRecord $record): AbstractEntryType
    {
        return $this->instantiate($record);
    }

    private function instantiate(EntryTypeRecord $record): AbstractEntryType
    {
        $class = $record->class;

        if (! class_exists($class)) {
            throw new RuntimeException("EntryType class [{$class}] does not exist.");
        }

        if (! is_subclass_of($class, AbstractEntryType::class)) {
            throw new RuntimeException("EntryType class [{$class}] must extend AbstractEntryType.");
        }

        return new $class($record);
    }
}
