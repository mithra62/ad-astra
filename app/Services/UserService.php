<?php

namespace App\Services;

use App\Concerns\PersistsFieldValues;
use App\Models\User;
use App\Models\User\OauthToken;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class UserService
{
    use PersistsFieldValues;

    // -------------------------------------------------------------------------
    // CRUD
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
        $attributes = Arr::except($data, ['roles', 'fields']);

        if (! empty($attributes['password'])) {
            $attributes['password'] = Hash::make($attributes['password']);
        }

        $user = User::create($attributes);

        if (! empty($data['roles'])) {
            $user->syncRoles((array) $data['roles']);
        }

        if (array_key_exists('fields', $data) && is_array($data['fields'])) {
            $this->setFields($user, $data['fields']);
        }

        return $user->refresh();
    }

    /**
     * Update a user's core attributes, roles, and/or custom fields.
     * Only keys present in $data are touched.
     */
    public function update(User $user, array $data): User
    {
        $attributes = Arr::except($data, ['password', 'roles', 'fields']);

        if (! empty($attributes)) {
            $user->update($attributes);
        }

        if (array_key_exists('roles', $data)) {
            $user->syncRoles((array) $data['roles']);
        }

        if (array_key_exists('fields', $data)) {
            $this->setFields($user, $data['fields']);
        }

        return $user->refresh();
    }

    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }

    // -------------------------------------------------------------------------
    // Roles
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
     * Replace all roles with the given set.
     */
    public function syncRoles(User $user, array $roles): User
    {
        $user->syncRoles($roles);

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

    // -------------------------------------------------------------------------
    // Passwords
    // -------------------------------------------------------------------------

    /**
     * Admin force-set — bypasses current-password verification.
     * Use this when an admin resets another user's password.
     */
    public function setPassword(User $user, string $newPassword): void
    {
        $user->forceFill(['password' => Hash::make($newPassword)])->save();
    }

    // -------------------------------------------------------------------------
    // Two-Factor Authentication
    //
    // Requires the User model to use:
    //   Laravel\Fortify\TwoFactorAuthenticatable
    // -------------------------------------------------------------------------

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
            'secret'      => decrypt($user->two_factor_secret),
        ];
    }

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
        return ! is_null($user->two_factor_confirmed_at);
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
     * Invalidate existing recovery codes and generate a fresh set.
     * Returns the new codes as a plain array.
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        app(GenerateNewRecoveryCodes::class)($user);

        $user->refresh();

        return $this->getRecoveryCodes($user);
    }

    // -------------------------------------------------------------------------
    // OAuth Token Management
    // -------------------------------------------------------------------------

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
