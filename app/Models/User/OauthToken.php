<?php


namespace App\Models\User;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OauthToken extends Model
{
    use HasFactory;

    protected $table = 'user_oauth_tokens';

    protected $fillable = [
        'user_id',

        // Provider identity
        'provider',
        'provider_account',
        'provider_user_id',

        // OpenID Connect
        'issuer',
        'subject',
        'id_token',

        // OAuth tokens
        'access_token',
        'refresh_token',
        'token_type',
        'expires_at',

        // Metadata
        'scopes',
        'meta',

        // Status
        'revoked_at',
        'last_used_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'meta' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Fortify user relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /* -----------------------------------------------------------------
     |  Query scopes
     | -----------------------------------------------------------------
     */

    /**
     * Tokens for a given provider (google, github, stripe, etc.)
     */
    public function scopeProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Only non-revoked tokens
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * Tokens that are expired or about to expire
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Tokens expiring within X seconds
     */
    public function scopeExpiringSoon(Builder $query, int $seconds = 300): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addSeconds($seconds)]);
    }

    /**
     * OIDC-based identity lookup
     */
    public function scopeOidcIdentity(
        Builder $query,
        string  $issuer,
        string  $subject
    ): Builder
    {
        return $query
            ->where('issuer', $issuer)
            ->where('subject', $subject);
    }

    /* -----------------------------------------------------------------
     |  Helpers
     | -----------------------------------------------------------------
     */

    public function isActive(): bool
    {
        return $this->revoked_at === null && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof CarbonInterface
            && $this->expires_at->isPast();
    }

    public function revoke(): void
    {
        $this->forceFill([
            'revoked_at' => now(),
        ])->save();
    }

    public function markUsed(): void
    {
        $this->forceFill([
            'last_used_at' => now(),
        ])->save();
    }

    /**
     * Convenience: check for a scope
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}
