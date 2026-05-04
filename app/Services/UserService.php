<?php

namespace App\Services;

use App\Models\User;
use App\Models\User\OauthToken;
use App\Traits\PersistsFieldValues;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

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
     * Only `name` is set on creation; password and roles are left for later.
     */
    public function firstOrCreateFromSocial(string $email, string $name): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(\Illuminate\Support\Str::random(32)),
            ],
        );
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
        $attributes = Arr::except($data, ['password', 'roles', 'fields']);

        if (!empty($attributes)) {
            $user->update($attributes);
        }

        if (array_key_exists('roles', $data)) {
            $user->syncRoles((array)$data['roles']);
        }

        if (array_key_exists('fields', $data)) {
            $this->setFields($user, $data['fields']);
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

    public function delete(User $user): bool
    {
        return (bool)$user->delete();
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
        // Revoke existing active tokens for this provider
        $user->oauthTokens()->provider($provider)->active()
            ->each(fn(OauthToken $t) => $t->revoke());

        return $user->oauthTokens()->create(
            array_merge($data, ['provider' => $provider])
        );
    }

    // -------------------------------------------------------------------------
    // OAuth Token Management
    // -------------------------------------------------------------------------

    /**
     * Create a user, optionally assigning roles and setting custom field values.
     *
     * Accepted keys in $data:
     *   name, email, title, phone, password  — core user attributes
     *   roles   (array)  — role names to sync
     *   fields  (array)  — ['handle' => value] custom field values
     */
    public function create(array $data): User
    {
        $attributes = Arr::except($data, ['roles', 'fields', 'password_confirmation']);

        if (!empty($attributes['password'])) {
            $attributes['password'] = Hash::make($attributes['password']);
        }

        $user = User::create($attributes);

        if (!empty($data['roles'])) {
            $user->syncRoles((array)$data['roles']);
        }

        if (array_key_exists('fields', $data) && is_array($data['fields'])) {
            $this->setFields($user, $data['fields']);
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

        $query->each(fn(OauthToken $t) => $t->revoke());
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
