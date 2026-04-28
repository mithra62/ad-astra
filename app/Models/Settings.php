<?php

/**
 * REMOVED — this flat key/value Settings model has been replaced by the
 * config-driven Settings service (App\Settings) with SettingDomain and
 * SettingValue models. This file is kept as a tombstone only.
 *
 * @deprecated
 */

namespace App\Models;

use RuntimeException;

/**
 * @deprecated Use App\Settings (service), App\Models\SettingDomain, and App\Models\SettingValue instead.
 */
class Settings
{
    public function __construct()
    {
        throw new RuntimeException(
            'App\Models\Settings is removed. Use App\Settings (the SettingsResolver service), '
            . 'App\Models\SettingDomain, or App\Models\SettingValue instead.'
        );
    }

    public static function __callStatic(string $name, array $arguments): never
    {
        throw new RuntimeException(
            'App\Models\Settings is removed. Use App\Settings (the SettingsResolver service), '
            . 'App\Models\SettingDomain, or App\Models\SettingValue instead.'
        );
    }
}
