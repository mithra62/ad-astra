<?php

namespace App\EntryTypes;

use App\Models\Entry;

class GeneralEntryType extends AbstractEntryType
{
    /**
     * Stamp published_at when an entry is created without an explicit date.
     */
    public function beforeCreate(array $data): array
    {
        if (empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    /**
     * Stamp published_at when transitioning to published without an explicit date.
     */
    public function beforeUpdate(Entry $entry, array $data): array
    {
        if (
            isset($data['status']) &&
            $data['status'] === 'published' &&
            empty($data['published_at']) &&
            !$entry->published_at
        ) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
