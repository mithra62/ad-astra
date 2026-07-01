<?php

namespace AdAstra\EntryTypes;

use AdAstra\Models\Entry;

class NewsArticleEntryType extends AbstractEntryType
{
    /**
     * Require source name when source_url is provided.
     *
     * {@inheritdoc}
     */
    public function validate(array $data, ?Entry $entry = null): array
    {
        $errors = [];

        $sourceUrl = $data['fields']['source_url'] ?? $this->existingFieldValue($entry, 'source_url');
        $source = $data['fields']['source'] ?? $this->existingFieldValue($entry, 'source');

        if (!empty($sourceUrl) && empty($source)) {
            $errors['source'] = 'Source name is required when a source URL is provided.';
        }

        return $errors;
    }
}
