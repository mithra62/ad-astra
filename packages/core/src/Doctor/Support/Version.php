<?php

namespace AdAstra\Doctor\Support;

/**
 * Canonical AdAstra version for doctor reports. The source of truth is the
 * `installed_version` field *default* in the settings config schema — a pure
 * config read that works on installs with no database. (The DB-stored
 * setting value only mirrors or lags this, mimicking the git-tag paradigm;
 * git tags take over as the source eventually.)
 */
final class Version
{
    public static function current(): string
    {
        foreach ((array) config('settings.general.fields', []) as $field) {
            if (($field['handle'] ?? null) === 'installed_version') {
                return (string) ($field['default'] ?? 'unknown');
            }
        }

        return 'unknown';
    }
}
