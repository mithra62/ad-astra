<?php

namespace App\EntryTypes;

use App\Models\Entry;

class BlogPostEntryType extends AbstractEntryType
{
    /**
     * Stamp published_at when created with a published status and no explicit date.
     * Compute reading_time from the body word count.
     */
    public function beforeCreate(array $data): array
    {
        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $this->computeReadingTime($data);
    }

    /**
     * Re-compute reading_time whenever body is present in the update payload.
     */
    public function beforeUpdate(Entry $entry, array $data): array
    {
        return $this->computeReadingTime($data);
    }

    // -------------------------------------------------------------------------

    private function computeReadingTime(array $data): array
    {
        $body = $data['fields']['body'] ?? null;

        if ($body !== null) {
            $data['fields']['reading_time'] = (int) ceil(str_word_count((string) $body) / 200);
        }

        return $data;
    }
}
