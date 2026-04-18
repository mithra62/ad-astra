<?php

namespace App\Facades;

use App\Models\User;
use App\Models\User\OauthToken;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static User   create(array $data)
 * @method static User   update(User $user, array $data)
 * @method static bool   delete(User $user)
 *
 * @method static User   assignRoles(User $user, array|string $roles)
 * @method static User   syncRoles(User $user, array $roles)
 * @method static User   revokeRole(User $user, string $role)
 *
 * @method static void   setField(User $user, string $slug, mixed $value)
 * @method static void   setFields(User $user, array $fields)
 *
 * @method static void   setPassword(User $user, string $newPassword)
 *
 * @method static array  enableTwoFactor(User $user)
 * @method static void   confirmTwoFactor(User $user, string $code)
 * @method static void   disableTwoFactor(User $user)
 * @method static bool   hasTwoFactor(User $user)
 * @method static array  getRecoveryCodes(User $user)
 * @method static array  regenerateRecoveryCodes(User $user)
 *
 * @method static OauthToken      upsertOauthToken(User $user, string $provider, array $data)
 * @method static OauthToken|null getActiveOauthToken(User $user, string $provider)
 * @method static void            revokeOauthToken(OauthToken $token)
 * @method static void            revokeAllOauthTokens(User $user, ?string $provider = null)
 * @method static Collection      listOauthTokens(User $user, ?string $provider = null)
 *
 * @see \App\Services\UserService
 */
class Users extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\UserService::class;
    }
}
