<?php

namespace App\EntryTypes;

use App\Models\Entry;

class NewsArticleEntryType extends AbstractEntryType
{
    /**
     * Auto-stamp published_at when an article is created with a live status.
     */
    public function beforeCreate(array $data): array
    {
        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    /**
     * Auto-stamp published_at when an article transitions to published
     * and hasn't been given an explicit date.
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

    /**
     * Require source name when source_url is provided.
     *
     * {@inheritdoc}
     */
    public function validate(array $data, ?Entry $entry = null): array
    {
        $errors = [];

        $sourceUrl = $data['fields']['source_url'] ?? $this->existingFieldValue($entry, 'source_url');
        $source    = $data['fields']['source']     ?? $this->existingFieldValue($entry, 'source');

        if (!empty($sourceUrl) && empty($source)) {
            $errors['source'] = 'Source name is required when a source URL is provided.';
        }

        return $errors;
    }
}
