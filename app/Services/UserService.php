<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Events\NewSocialUserRegistered;
use App\Events\UserLockChanged;
use App\Events\UserStatusChanged;
use App\Models\Entry;
use App\Models\EntryAuthor;
use App\Models\User;
use App\Models\User\OauthToken;
use App\Settings;
use App\Traits\Field\PersistsFieldValues;
use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Auth\Access\AuthorizationException;

class UserService
{
    use PersistsFieldValues;

    // -------------------------------------------------------------------------
    // Retrieval
    // -------------------------------------------------------------------------

    /**
     * Find a User by ID. Returns null when not found.
     */
    public function find(int $id): ?User
    {
        return User::with('tokens')->find($id);
    }

    /**
     * Return a paginated list of users, eager-loading the given relations.
     *
     * @param int $perPage Records per page (default 20)
     * @param array $with Relations to eager-load (default ['roles'])
     */
    public function paginate(int $perPage = 20, array $with = ['roles']): LengthAwarePaginator
    {
        return User::with($with)->paginate($perPage);
    }

    /**
     * Return a lightweight collection of users suitable for select/dropdown UI.
     * Only id and name are loaded; ordered alphabetically.
     */
    public function getForDropdown(int $limit = 50): Collection
    {
        return User::select(['id', 'name'])
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Find a User by ID. Throws ModelNotFoundException when not found.
     */
    public function get(int $id): User
    {
        return User::findOrFail($id);
    }

    /**
     * Return the total number of users in the database.
     */
    public function getTotalCount(): int
    {
        return User::count();
    }

    /**
     * Return the most recently created users, ordered newest-first.
     */
    public function getLatestUsers(int $limit = 9): Collection
    {
        return User::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find a user by email or create one for a social-login callback.
     *
     * New accounts are given the status configured under
     * Settings users.social_default_status (default: 'pending').
     * A NewSocialUserRegistered event is fired for new accounts only.
     */
    public function firstOrCreateFromSocial(string $email, string $name, string $provider, string $ip): User
    {
        $created = false;

        $socialDefaultStatus = app(Settings::class)->get('users', 'social_default_status')
            ?? UserStatus::PENDING;

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => Hash::make(\Illuminate\Support\Str::random(32)),
                'status'   => $socialDefaultStatus,
            ],
        );

        if ($user->wasRecentlyCreated) {
            $created = true;
        }

        if ($created) {
            event(new NewSocialUserRegistered($user, $provider, $ip));
        }

        return $user;
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Add one or more roles without removing existing ones.
     */
    public function assignRoles(User $user, array|string $roles): User
    {
        $user->assignRole($roles);

        return $user;
    }

    /**
     * Remove a single role.
     */
    public function revokeRole(User $user, string $role): User
    {
        $user->removeRole($role);

        return $user;
    }

    /**
     * Admin force-set — bypasses current-password verification.
     * Use this when an admin resets another user's password.
     */
    public function setPassword(User $user, string $newPassword): void
    {
        $user->forceFill(['password' => Hash::make($newPassword)])->save();
    }

    // -------------------------------------------------------------------------
    // Roles
    // -------------------------------------------------------------------------

    /**
     * Issue a new personal access token for a user.
     *
     * @param string $name Token display name
     * @param array $abilities Sanctum ability strings (default ['*'])
     * @param Carbon|null $expiresAt Optional expiry timestamp
     */
    public function createToken(User $user, string $name, array $abilities = ['*'], ?Carbon $expiresAt = null): NewAccessToken
    {
        return $user->createToken($name, $abilities, $expiresAt);
    }

    /**
     * Update a personal access token's attributes (e.g. name, abilities, expires_at).
     * Returns null when the token does not exist or belongs to another user.
     */
    public function updateToken(User $user, int|string $tokenId, array $data): ?PersonalAccessToken
    {
        $token = $this->getToken($user, $tokenId);

        if (!$token instanceof PersonalAccessToken) {
            return null;
        }

        $token->update($data);

        return $token->refresh();
    }

    /**
     * Retrieve a single personal access token belonging to a user.
     * Returns null when the token does not exist or belongs to another user.
     */
    public function getToken(User $user, int|string $tokenId): ?PersonalAccessToken
    {
        /** @var PersonalAccessToken|null $token */
        $token = $user->tokens()->where('id', $tokenId)->first();

        return $token;
    }

    // -------------------------------------------------------------------------
    // Passwords
    // -------------------------------------------------------------------------

    /**
     * Update a user's core attributes, roles, and/or custom fields.
     * Only keys present in $data are touched.
     */
    public function update(User $user, array $data): User
    {
        // 'status' and its companion columns are intentionally excluded here.
        // Account status must only change through setStatus(), suspend(),
        // lockUser(), or unlockUser() so that the audit log is always written,
        // events are fired, and companion columns (banned_at, suspended_until)
        // are kept in sync.  Any 'status' key in $data is silently ignored.
        $attributes = Arr::except($data, [
            'password',
            'roles',
            'fields',
            'is_author',
            'author_display_name',
            'status',
            'suspended_until',
            'banned_at',
            'locked_until',
        ]);

        if (!empty($attributes)) {
            $user->update($attributes);
        }

        if (array_key_exists('roles', $data)) {
            $user->syncRoles((array)$data['roles']);
        }

        if (array_key_exists('fields', $data)) {
            $this->setFields($user, $data['fields']);
        }

        if (array_key_exists('is_author', $data)) {
            app(EntryAuthorService::class)->sync(
                $user,
                (bool) $data['is_author'],
                $data['author_display_name'] ?? null,
            );
        }

        return $user->refresh();
    }

    // -------------------------------------------------------------------------
    // Sanctum Personal Access Tokens
    // -------------------------------------------------------------------------

    /**
     * Replace all roles with the given set.
     */
    public function syncRoles(User $user, array $roles): User
    {
        $actor = auth()->user();

        if (in_array('super admin', $roles, true) && ! $actor?->hasRole('super admin')) {
            throw AuthorizationException::class
                ::denyAsNotFound('Only a super admin may assign the super admin role.');
        }

        $user->syncRoles($roles);
        return $user;
    }

    /**
     * Revoke (delete) a personal access token belonging to a user.
     * Returns true when deleted, false when the token was not found.
     */
    public function revokeToken(User $user, int|string $tokenId): bool
    {
        return (bool)$user->tokens()->where('id', $tokenId)->delete();
    }

    // -------------------------------------------------------------------------
    // Status management
    // -------------------------------------------------------------------------

    /**
     * Set an administrative status on a user account.
     *
     * Automatically manages the banned_at timestamp and fires UserStatusChanged.
     * Do not use this method for suspensions — use suspend() instead.
     *
     * @param string $newStatus  One of UserStatus::ALL
     * @param string|null $reason  Optional reason stored in the audit log
     */
    public function setStatus(User $user, string $newStatus, ?string $reason = null): User
    {
        $previousStatus = $user->status;

        $update = ['status' => $newStatus];

        // Keep banned_at in sync with the banned status.
        if ($newStatus === UserStatus::BANNED && $previousStatus !== UserStatus::BANNED) {
            $update['banned_at'] = now();
        } elseif ($newStatus !== UserStatus::BANNED) {
            $update['banned_at'] = null;
        }

        // Clear suspended_until if we're moving away from suspended.
        if ($newStatus !== UserStatus::SUSPENDED) {
            $update['suspended_until'] = null;
        }

        $user->forceFill($update)->save();

        event(new UserStatusChanged($user, $previousStatus, $newStatus, $reason, []));

        return $user->refresh();
    }

    /**
     * Suspend a user until a given datetime.
     *
     * Sets status to 'suspended', records suspended_until, and fires
     * UserStatusChanged with the context array containing the expiry.
     */
    public function suspend(User $user, DateTime $until, string $reason = ''): User
    {
        $previousStatus = $user->status;

        $user->forceFill([
            'status'         => UserStatus::SUSPENDED,
            'suspended_until' => $until,
            'banned_at'      => null,
        ])->save();

        event(new UserStatusChanged(
            $user,
            $previousStatus,
            UserStatus::SUSPENDED,
            $reason ?: null,
            ['suspended_until' => $until->format('Y-m-d H:i:s')],
        ));

        return $user->refresh();
    }

    /**
     * Lock a user account until a given datetime (parallel to status).
     *
     * Fires UserLockChanged.
     */
    public function lockUser(User $user, DateTime $until, string $reason = ''): User
    {
        $previousLockedUntil = $user->locked_until;

        $user->forceFill(['locked_until' => $until])->save();

        event(new UserLockChanged($user, $previousLockedUntil, $until, $reason));

        return $user->refresh();
    }

    /**
     * Remove an account lock, allowing the user to log in again (subject to status).
     *
     * Fires UserLockChanged.
     */
    public function unlockUser(User $user): User
    {
        $previousLockedUntil = $user->locked_until;

        $user->forceFill(['locked_until' => null])->save();

        event(new UserLockChanged($user, $previousLockedUntil, null, ''));

        return $user->refresh();
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function delete(User $user): bool
    {
        $hasCreatedEntries = Entry::where('created_by_user_id', $user->id)->exists();
        $hasAuthoredEntries = EntryAuthor::where('user_id', $user->id)->exists();

        if ($hasCreatedEntries || $hasAuthoredEntries) {
            throw ValidationException::withMessages([
                'user' => 'This user has associated content and cannot be deleted. Reassign or remove their entries first.',
            ]);
        }

        return (bool) $user->delete();
    }

    /**
     * Begin 2FA setup. Returns the QR code SVG and the plain-text secret
     * so they can be displayed to the user for scanning.
     *
     * The user must still call confirmTwoFactor() with a valid TOTP code
     * before 2FA is considered active (two_factor_confirmed_at is set).
     *
     * @return array{qr_code_svg: string, secret: string}
     */
    public function enableTwoFactor(User $user): array
    {
        app(EnableTwoFactorAuthentication::class)($user);

        $user->refresh();

        return [
            'qr_code_svg' => $user->twoFactorQrCodeSvg(),
            'secret' => decrypt($user->two_factor_secret),
        ];
    }

    // -------------------------------------------------------------------------
    // Two-Factor Authentication
    //
    // Requires the User model to use:
    //   Laravel\Fortify\TwoFactorAuthenticatable
    // -------------------------------------------------------------------------

    /**
     * Confirm 2FA setup with a valid TOTP code from the authenticator app.
     * Sets two_factor_confirmed_at on success.
     *
     * @throws \Illuminate\Validation\ValidationException  if the code is invalid
     */
    public function confirmTwoFactor(User $user, string $code): void
    {
        app(ConfirmTwoFactorAuthentication::class)($user, $code);
    }

    /**
     * Disable 2FA and clear the secret and recovery codes.
     */
    public function disableTwoFactor(User $user): void
    {
        app(DisableTwoFactorAuthentication::class)($user);
    }

    /**
     * Whether 2FA is fully confirmed and active for this user.
     */
    public function hasTwoFactor(User $user): bool
    {
        return !is_null($user->two_factor_confirmed_at);
    }

    /**
     * Invalidate existing recovery codes and generate a fresh set.
     * Returns the new codes as a plain array.
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        app(GenerateNewRecoveryCodes::class)($user);

        $user->refresh();

        return $this->getRecoveryCodes($user);
    }

    /**
     * Return the user's current 2FA recovery codes as a plain array.
     * Returns an empty array if 2FA is not set up.
     */
    public function getRecoveryCodes(User $user): array
    {
        if (empty($user->two_factor_recovery_codes)) {
            return [];
        }

        return json_decode(decrypt($user->two_factor_recovery_codes), true) ?? [];
    }

    /**
     * Store a new OAuth token for a provider, revoking any existing active token
     * for that provider first to prevent duplicates.
     *
     * $data should contain any combination of the OauthToken fillable columns:
     *   provider_account, provider_user_id, access_token, refresh_token,
     *   token_type, expires_at, scopes, issuer, subject, id_token, meta
     */
    public function upsertOauthToken(User $user, string $provider, array $data): OauthToken
    {
        return DB::transaction(function () use ($user, $provider, $data): OauthToken {
            // Revoke existing active tokens for this provider in a single UPDATE.
            // Bypasses Eloquent model events intentionally — no listeners exist on
            // OauthToken. If that changes, dispatch a domain event from here instead.
            $user->oauthTokens()->provider($provider)->active()
                ->update(['revoked_at' => now()]);

            return $user->oauthTokens()->create(
                array_merge($data, ['provider' => $provider])
            );
        });
    }

    // -------------------------------------------------------------------------
    // OAuth Token Management
    // -------------------------------------------------------------------------

    /**
     * Create a user, optionally assigning roles and setting custom field values.
     *
     * Accepted keys in $data:
     *   name, email, title, phone, password  — core user attributes
     *   status  (string) — one of UserStatus::CREATION_ALLOWED; defaults to the
     *                      system setting users.default_status (fallback: 'active')
     *   roles   (array)  — role names to sync
     *   fields  (array)  — ['handle' => value] custom field values
     */
    public function create(array $data): User
    {
        $attributes = Arr::except($data, ['roles', 'fields', 'password_confirmation', 'is_author', 'author_display_name']);

        if (!empty($attributes['password'])) {
            $attributes['password'] = Hash::make($attributes['password']);
        }

        // Apply system default status when none is supplied.
        if (empty($attributes['status'])) {
            $attributes['status'] = app(Settings::class)->get('users', 'default_status')
                ?? UserStatus::ACTIVE;
        }

        $user = User::create($attributes);

        if (!empty($data['roles'])) {
            $user->syncRoles((array)$data['roles']);
        }

        if (array_key_exists('fields', $data) && is_array($data['fields'])) {
            $this->setFields($user, $data['fields']);
        }

        if (array_key_exists('is_author', $data)) {
            app(EntryAuthorService::class)->sync(
                $user,
                (bool) $data['is_author'],
                $data['author_display_name'] ?? null,
            );
        }

        return $user->refresh();
    }

    /**
     * Get the most recently issued active token for a given provider.
     * Returns null if none exists or the token has expired/been revoked.
     */
    public function getActiveOauthToken(User $user, string $provider): ?OauthToken
    {
        return $user->oauthTokenFor($provider);
    }

    /**
     * Revoke a single OAuth token.
     */
    public function revokeOauthToken(OauthToken $token): void
    {
        $token->revoke();
    }

    /**
     * Revoke all active OAuth tokens for a user, optionally filtered by provider.
     */
    public function revokeAllOauthTokens(User $user, ?string $provider = null): void
    {
        $query = $user->oauthTokens()->active();

        if ($provider) {
            $query->provider($provider);
        }

        $query->update(['revoked_at' => now()]);
    }

    /**
     * List all active OAuth tokens for a user, optionally filtered by provider.
     */
    public function listOauthTokens(User $user, ?string $provider = null): Collection
    {
        $query = $user->oauthTokens()->active();

        if ($provider) {
            $query->provider($provider);
        }

        return $query->orderByDesc('created_at')->get();
    }
}
