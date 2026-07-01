<?php

namespace AdAstra\Facades;

use AdAstra\Models\User;
use AdAstra\Models\User\OauthToken;
use AdAstra\Services\UserService;
use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Facade;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Retrieval
 * @method static User|null find(int $id)
 * @method static User get(int $id)
 * @method static LengthAwarePaginator paginate(int $perPage = 20, array $with = ['roles'])
 * @method static Collection getForDropdown(int $limit = 50)
 * @method static int getTotalCount()
 * @method static Collection getLatestUsers(int $limit = 9)
 * @method static User firstOrCreateFromSocial(string $email, string $name, string $provider, string $ip)
 *
 * CRUD
 * @method static User create(array $data)
 * @method static User update(User $user, array $data)
 * @method static bool delete(User $user)
 *
 * Status management
 * @method static User setStatus(User $user, string $newStatus, ?string $reason = null)
 * @method static User suspend(User $user, DateTime $until, string $reason = '')
 * @method static User lockUser(User $user, DateTime $until, string $reason = '')
 * @method static User unlockUser(User $user)
 *
 * Roles
 * @method static User assignRoles(User $user, array|string $roles)
 * @method static User syncRoles(User $user, array $roles)
 * @method static User revokeRole(User $user, string $role)
 *
 * Fields
 * @method static void setField(User $user, string $handle, mixed $value)
 * @method static void setFields(User $user, array $fields)
 *
 * Passwords
 * @method static void setPassword(User $user, string $newPassword)
 *
 * Sanctum tokens
 * @method static NewAccessToken createToken(User $user, string $name, array $abilities = ['*'], Carbon|null $expiresAt = null)
 * @method static PersonalAccessToken|null getToken(User $user, int|string $tokenId)
 * @method static PersonalAccessToken|null updateToken(User $user, int|string $tokenId, array $data)
 * @method static bool revokeToken(User $user, int|string $tokenId)
 *
 * Two-Factor Authentication
 * @method static array enableTwoFactor(User $user)
 * @method static void confirmTwoFactor(User $user, string $code)
 * @method static void disableTwoFactor(User $user)
 * @method static bool hasTwoFactor(User $user)
 * @method static array getRecoveryCodes(User $user)
 * @method static array regenerateRecoveryCodes(User $user)
 *
 * OAuth tokens
 * @method static OauthToken upsertOauthToken(User $user, string $provider, array $data)
 * @method static OauthToken|null getActiveOauthToken(User $user, string $provider)
 * @method static void revokeOauthToken(OauthToken $token)
 * @method static void revokeAllOauthTokens(User $user, ?string $provider = null)
 * @method static Collection listOauthTokens(User $user, ?string $provider = null)
 *
 * @see UserService
 */
class Users extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return UserService::class;
    }
}
