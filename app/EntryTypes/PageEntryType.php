<?php

namespace App\EntryTypes;

class PageEntryType extends AbstractEntryType
{
    /**
     * Pages go live immediately on creation unless an explicit date is provided.
     */
    public function beforeCreate(array $data): array
    {
        if (empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
