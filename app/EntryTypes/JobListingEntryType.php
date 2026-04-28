<?php

namespace App\EntryTypes;

use App\Models\Entry;
use Carbon\Carbon;

class JobListingEntryType extends AbstractEntryType
{
    public function beforeCreate(array $data): array
    {
        if (empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    public function beforeUpdate(Entry $entry, array $data): array
    {
        if (isset($data['status']) && in_array($data['status'], ['expired', 'closed'], true)) {
            $data['published_at'] = null;

            return $data;
        }

        $closingRaw = $data['fields']['closing_date']
            ?? $this->existingFieldValue($entry, 'closing_date');

        if ($closingRaw !== null) {
            $closing = $closingRaw instanceof Carbon
                ? $closingRaw
                : Carbon::parse($closingRaw);

            if (now()->gt($closing)) {
                $data['status']       = 'expired';
                $data['published_at'] = null;
            }
        }

        return $data;
    }

    public function validate(array $data, ?Entry $entry = null): array
    {
        $errors = [];

        $requestedStatus = $data['status'] ?? ($entry?->status_handle);

        if ($requestedStatus === 'published') {
            $url   = $data['fields']['application_url']   ?? $this->existingFieldValue($entry, 'application_url');
            $email = $data['fields']['application_email'] ?? $this->existingFieldValue($entry, 'application_email');

            if (empty($url) && empty($email)) {
                $errors['application_url'] = 'A job listing must have either an application URL or an application email before publishing.';
            }
        }

        return $errors;
    }
}
