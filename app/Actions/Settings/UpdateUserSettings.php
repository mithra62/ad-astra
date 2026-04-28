<?php

namespace App\Actions\Settings;

use App\Actions\AbstractAction;
use App\Models\SettingDomain;
use App\Models\User;
use App\Settings;

/**
 * Persists a validated, normalised set of user preference overrides.
 *
 * The flat $data array (keyed by field handle) is distributed across domains
 * by matching each domain's overridable fields against the submitted keys.
 * Only overridable field handles are written; anything else in $data is
 * silently ignored. Cache invalidation is handled per-domain by Settings::setMany().
 */
class UpdateUserSettings extends AbstractAction
{
    /**
     * @param  User                 $user  The authenticated user whose overrides are being saved.
     * @param  array<string, mixed> $data  Normalised overridable field values keyed by handle.
     */
    public function execute(User $user, array $data): void
    {
        foreach (SettingDomain::ordered()->get() as $domain) {
            $overridableFields = $domain->overridableConfigFields();

            if (empty($overridableFields)) {
                continue;
            }

            $toWrite = [];

            foreach ($overridableFields as $field) {
                if (array_key_exists($field['handle'], $data)) {
                    $toWrite[$field['handle']] = $data[$field['handle']];
                }
            }

            if (! empty($toWrite)) {
                app(Settings::class)->setMany($domain->handle, $toWrite, user: $user);
            }
        }
    }
}
