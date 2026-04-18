<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Relationship extends AbstractField
{
    /**
     * Relationship fields store data in entry_relationships, not field_values.
     * This method satisfies the abstract contract but is never called.
     */
    public function storageColumn(): string
    {
        return 'value_json';
    }

    public function isRelational(): bool
    {
        return true;
    }

    /**
     * Validate that the value is an array of IDs (or empty/null).
     */
    public function validate(mixed $value): bool|string
    {
        if ($value === null || $value === []) {
            return true;
        }

        if (! is_array($value)) {
            return 'Relationship field value must be an array of entry IDs.';
        }

        $limit = $this->getSetting('limit');
        if ($limit && count($value) > $limit) {
            return "Relationship field may not exceed {$limit} related entries.";
        }

        return true;
    }
}
