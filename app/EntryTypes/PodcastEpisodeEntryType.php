<?php

namespace App\EntryTypes;

use App\Models\Entry;
use App\Models\EntryGroup;

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

        if (empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
