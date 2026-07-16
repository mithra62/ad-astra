<?php

namespace AdAstra\EntryTypes;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use InvalidArgumentException;

class PodcastEpisodeEntryType extends AbstractEntryType
{
    /**
     * Auto-assign the next episode number if the caller hasn't set one.
     *
     * Locks the EntryGroup row for the duration of the surrounding transaction
     * (started by EntryRepository::create) so that concurrent creates queue
     * behind each other. The count is taken after the lock is acquired, meaning
     * it reflects any episode committed immediately before this request, giving
     * a correct sequential number.
     *
     * The episode_number field must exist in the entry's field layout for the
     * value to be persisted — it is silently skipped otherwise.
     */
    public function beforeCreate(array $data): array
    {
        if (!isset($data['fields']['episode_number'])) {
            $groupId = $this->getRecord()->entry_group_id;

            // Lock the group row so concurrent episode creates serialize here.
            // The lock is released when EntryRepository's transaction commits.
            EntryGroup::where('id', $groupId)->lockForUpdate()->first();

            $data['fields']['episode_number'] = Entry::where('entry_group_id', $groupId)->count() + 1;
        }

        return $data;
    }

    /**
     * Validate that episode_duration, when provided, is a positive integer.
     */
    public function beforeUpdate(Entry $entry, array $data): array
    {
        $duration = $data['fields']['episode_duration'] ?? null;

        if ($duration !== null && (!is_int($duration) || $duration <= 0)) {
            throw new InvalidArgumentException(
                'episode_duration must be a positive integer (seconds).'
            );
        }

        return $data;
    }
}
