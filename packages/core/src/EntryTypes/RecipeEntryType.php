<?php

namespace AdAstra\EntryTypes;

use AdAstra\Models\Entry;

class RecipeEntryType extends AbstractEntryType
{
    /**
     * Compute total_time from prep + cook times.
     */
    public function beforeCreate(array $data): array
    {
        return $this->computeTotalTime($data);
    }

    /**
     * Re-compute total_time whenever prep_time or cook_time is in the payload.
     */
    public function beforeUpdate(Entry $entry, array $data): array
    {
        if (isset($data['fields']['prep_time']) || isset($data['fields']['cook_time'])) {
            $prepTime = $data['fields']['prep_time']
                ?? (int) $this->existingFieldValue($entry, 'prep_time');

            $cookTime = $data['fields']['cook_time']
                ?? (int) $this->existingFieldValue($entry, 'cook_time');

            $data['fields']['total_time'] = (int) $prepTime + (int) $cookTime;
        }

        return $data;
    }

    // -------------------------------------------------------------------------

    private function computeTotalTime(array $data): array
    {
        $prepTime = $data['fields']['prep_time'] ?? null;
        $cookTime = $data['fields']['cook_time'] ?? null;

        if ($prepTime !== null || $cookTime !== null) {
            $data['fields']['total_time'] = (int) $prepTime + (int) $cookTime;
        }

        return $data;
    }
}
