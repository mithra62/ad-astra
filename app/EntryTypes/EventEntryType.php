<?php

namespace App\EntryTypes;

use App\Models\Entry;
use Carbon\Carbon;
use InvalidArgumentException;

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

        $this->validateDateRange($entry, $data);

        return $data;
    }

    // -------------------------------------------------------------------------

    private function validateDateRange(Entry $entry, array $data): void
    {
        $endRaw   = $data['fields']['end_date']   ?? null;
        $startRaw = $data['fields']['start_date'] ?? $this->existingFieldValue($entry, 'start_date');

        if ($endRaw === null || $startRaw === null) {
            return;
        }

        $end   = Carbon::parse($endRaw);
        $start = $startRaw instanceof Carbon ? $startRaw : Carbon::parse($startRaw);

        if ($end->lt($start)) {
            throw new InvalidArgumentException('end_date cannot be earlier than start_date.');
        }
    }
}
