<?php

namespace AdAstra\Actions\Field\Concerns;

use AdAstra\Field\AbstractField;

trait FiltersFieldSettings
{
    /**
     * Sanitise submitted settings against the field type's blueprint: keep only
     * declared handles, fill missing ones with defaults, normalise key_value
     * rows. Delegates to the shared Blueprint subsystem.
     */
    private function filterSettings(array $raw, AbstractField $instance): array
    {
        return $instance->blueprint()->filter($raw);
    }
}
