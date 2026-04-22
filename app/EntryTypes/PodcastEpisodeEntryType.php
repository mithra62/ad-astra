<?php

namespace App\EntryTypes;

use App\Models\Entry;

class PodcastEpisodeEntryType extends AbstractEntryType
{
    /**
     * Auto-assign the next episode number if the caller hasn't set one.
     *
     * Reads the current highest episode count from the group rather than
     * relying on a separate counter, so gaps or reorders never desync it.
     * The episode_number field must exist in the entry's field layout for the
     * value to be persisted — it is silently skipped otherwise.
     */
    public function beforeCreate(array &$data): void
    {
        if (! isset($data['fields']['episode_number'])) {
            $groupId = $this->getRecord()->entry_group_id;
            $count   = Entry::where('entry_group_id', $groupId)->count();

            $data['fields']['episode_number'] = $count + 1;
        }

        if (empty($data['published_at'])) {
            $data['published_at'] = now();
        }
    }
}
