<?php

namespace AdAstra\Actions\Field\Concerns;

use AdAstra\Field\AbstractField;

trait FiltersFieldSettings
{
    private function filterSettings(array $raw, AbstractField $instance): array
    {
        $form = $instance->settingsForm();
        $defaults = $instance->settingsDefaults();
        $filtered = [];

        foreach ($form as $key => $def) {
            if (array_key_exists($key, $raw)) {
                $value = $raw[$key];
                if (($def['type'] ?? '') === 'key_value') {
                    $value = $this->normaliseKeyValue((array)$value);
                }
                $filtered[$key] = $value;
            } else {
                $filtered[$key] = $defaults[$key] ?? null;
            }
        }

        return $filtered;
    }

    private function normaliseKeyValue(array $raw): array
    {
        return array_values(
            array_filter(
                $raw,
                fn($row) => trim($row['key'] ?? '') !== ''
            )
        );
    }
}
