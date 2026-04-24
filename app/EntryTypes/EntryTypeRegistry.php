<?php

namespace App\EntryTypes;

use App\Models\EntryType as EntryTypeRecord;
use RuntimeException;

class EntryTypeRegistry
{
    /** @var array<string, AbstractEntryType> */
    private array $handleCache = [];

    /** @var array<int, AbstractEntryType> */
    private array $idCache = [];

    public function resolveByHandle(string $handle): AbstractEntryType
    {
        if (! isset($this->handleCache[$handle])) {
            $record = EntryTypeRecord::where('handle', $handle)
                ->with(['entryGroup', 'fieldLayout.tabs.elements.field.fieldType'])
                ->firstOrFail();

            $instance = $this->instantiate($record);
            $this->handleCache[$handle] = $instance;
            $this->idCache[$record->getKey()] = $instance;
        }

        return $this->handleCache[$handle];
    }

    public function resolveByRecord(EntryTypeRecord $record): AbstractEntryType
    {
        $id = $record->getKey();

        if (! isset($this->idCache[$id])) {
            $this->idCache[$id] = $this->instantiate($record);
        }

        return $this->idCache[$id];
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
