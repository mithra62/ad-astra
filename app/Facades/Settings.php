<?php

namespace App\Facades;

use App\Models\User;
use App\Settings as SettingsService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $domain, string $handle, mixed $default = null, ?User $user = null)
 * @method static array all(string $domain, ?User $user = null)
 *
 * @see SettingsService
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsService::class;
    }
}
