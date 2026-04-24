<?php

namespace App\EntryTypes;

use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryType as EntryTypeRecord;

abstract class AbstractEntryType
{
    public function __construct(protected EntryTypeRecord $record) {}

    public function getRecord(): EntryTypeRecord
    {
        return $this->record;
    }

    public function getName(): string
    {
        return $this->record->name;
    }

    public function getHandle(): string
    {
        return $this->record->handle;
    }

    public function getEntryGroup(): EntryGroup
    {
        return $this->record->entryGroup;
    }

    // Lifecycle hooks — override in concrete types for custom behaviour

    public function beforeCreate(array $data): array { return $data; }

    public function afterCreate(Entry $entry, array $data): void {}

    public function beforeUpdate(Entry $entry, array $data): array { return $data; }

    public function afterUpdate(Entry $entry, array $data): void {}
}
