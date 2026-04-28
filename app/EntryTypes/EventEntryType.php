<?php

namespace App\EntryTypes;

use App\Models\Entry;
use Carbon\Carbon;

class EventEntryType extends AbstractEntryType
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
     * Guard the date range: end_date must not be earlier than start_date.
     *
     * Only validated when end_date is present in the payload; start_date is
     * read from the payload first, then from the existing entry if absent.
     * Skipped silently when either date cannot be resolved.
     *
     * {@inheritdoc}
     */
    public function validate(array $data, ?Entry $entry = null): array
    {
        $errors = [];

        $endRaw   = $data['fields']['end_date']   ?? null;
        $startRaw = $data['fields']['start_date'] ?? $this->existingFieldValue($entry, 'start_date');

        if ($endRaw === null || $startRaw === null) {
            return $errors;
        }

        $end   = Carbon::parse($endRaw);
        $start = $startRaw instanceof Carbon ? $startRaw : Carbon::parse($startRaw);

        if ($end->lt($start)) {
            $errors['end_date'] = 'end_date cannot be earlier than start_date.';
        }

        return $errors;
    }
}
