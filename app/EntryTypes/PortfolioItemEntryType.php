<?php

namespace App\EntryTypes;

class PortfolioItemEntryType extends AbstractEntryType
{
    /**
     * Portfolio items go live immediately on creation unless an explicit date is provided.
     */
    public function beforeCreate(array $data): array
    {
        if (empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
