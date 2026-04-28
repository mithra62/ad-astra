<?php

namespace App\EntryTypes;

use App\Models\Entry;

class VideoEntryType extends AbstractEntryType
{
    /**
     * Videos go live immediately on creation unless an explicit date is provided.
     */
    public function beforeCreate(array $data): array
    {
        if (empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    /**
     * Require at least one of platform_id or video_url when publishing.
     *
     * {@inheritdoc}
     */
    public function validate(array $data, ?Entry $entry = null): array
    {
        $errors = [];

        $requestedStatus = $data['status'] ?? ($entry?->status_handle);

        if ($requestedStatus === 'published') {
            $platformId = $data['fields']['platform_id'] ?? $this->existingFieldValue($entry, 'platform_id');
            $videoUrl   = $data['fields']['video_url']   ?? $this->existingFieldValue($entry, 'video_url');

            if (empty($platformId) && empty($videoUrl)) {
                $errors['platform_id'] = 'A video must have either a platform ID or a video URL before publishing.';
            }
        }

        return $errors;
    }
}
