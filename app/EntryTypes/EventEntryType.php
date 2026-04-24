<?php

namespace App\EntryTypes;

use App\Models\Entry;

class EventEntryType extends AbstractEntryType
{
    /**
     * Default published_at to now so the event is immediately queryable.
     * If a caller passes an explicit published_at, it is respected.
     */
    public function beforeCreate(array $data): array
    {
        if (empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    /**
     * Stamp published_at when the event is explicitly published and has no date yet.
     */
    public function beforeUpdate(Entry $entry, array $data): array
    {
        if (
            isset($data['status']) &&
            $data['status'] === 'published' &&
            empty($data['published_at']) &&
            ! $entry->published_at
        ) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
