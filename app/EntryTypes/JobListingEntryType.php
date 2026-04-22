<?php

namespace App\EntryTypes;

use App\Models\Entry;

class JobListingEntryType extends AbstractEntryType
{
    /**
     * Job listings go live immediately unless a specific publish date is provided.
     */
    public function beforeCreate(array &$data): void
    {
        if (empty($data['published_at'])) {
            $data['published_at'] = now();
        }
    }

    /**
     * When a listing is closed/expired, clear published_at so it drops out of
     * published-entry queries without requiring a status scope on every call.
     */
    public function beforeUpdate(Entry $entry, array &$data): void
    {
        if (isset($data['status']) && in_array($data['status'], ['expired', 'closed'], true)) {
            $data['published_at'] = null;
        }
    }
}
