<?php

namespace App\Actions\Settings;

use App\Actions\AbstractAction;
use App\Settings;

/**
 * Persists a validated, normalised set of field values as system-level
 * settings for a single domain.
 *
 * The caller is responsible for validation and boolean normalisation — this
 * action simply delegates to the Settings service which handles typed column
 * mapping and cache invalidation.
 */
class UpdateDomainSettings extends AbstractAction
{
    /**
     * @param string $handle Domain handle (e.g. 'general', 'media').
     * @param array<string, mixed> $data Normalised field values keyed by handle.
     */
    public function execute(string $handle, array $data): void
    {
        app(Settings::class)->setMany($handle, $data, user: null);
    }
}
