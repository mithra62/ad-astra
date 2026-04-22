<?php

namespace App\EntryTypes;

use App\Models\Entry;

class NewsArticleEntryType extends AbstractEntryType
{
    /**
     * Auto-stamp published_at when an article is created with a live status.
     */
    public function beforeCreate(array &$data): void
    {
        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
    }

    /**
     * Auto-stamp published_at when an article transitions to published
     * and hasn't been given an explicit date.
     */
    public function beforeUpdate(Entry $entry, array &$data): void
    {
        if (
            isset($data['status']) &&
            $data['status'] === 'published' &&
            empty($data['published_at']) &&
            ! $entry->published_at
        ) {
            $data['published_at'] = now();
        }
    }
}
