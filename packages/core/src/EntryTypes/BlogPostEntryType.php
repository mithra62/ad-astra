<?php

namespace AdAstra\EntryTypes;

use AdAstra\Models\Entry;

class BlogPostEntryType extends AbstractEntryType
{
    /**
     * Compute reading_time from the body word count.
     */
    public function beforeCreate(array $data): array
    {
        return $this->computeReadingTime($data);
    }

    private function computeReadingTime(array $data): array
    {
        $body = $data['fields']['body'] ?? null;

        if ($body !== null) {
            $data['fields']['reading_time'] = (int)ceil(str_word_count((string)$body) / 200);
        }

        return $data;
    }

    // -------------------------------------------------------------------------

    /**
     * Re-compute reading_time whenever body is present in the update payload.
     */
    public function beforeUpdate(Entry $entry, array $data): array
    {
        return $this->computeReadingTime($data);
    }
}
