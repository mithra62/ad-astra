<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use App\Models\User\OauthToken;
use App\Traits\Field\Fieldable;
use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravolt\Avatar\Facade as LaravoltAvatar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, Fieldable, TwoFactorAuthenticatable, HasMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'suspended_until',
        'banned_at',
        'locked_until',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'suspended_until' => 'datetime',
        'banned_at' => 'datetime',
        'locked_until' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Access helpers
    // -------------------------------------------------------------------------

    /**
     * Whether this user is permitted to access the system right now.
     *
     * Checks both the administrative status and the parallel lock flag.
     * Auto-expiry for suspended_until and locked_until is handled here at
     * runtime.
     */
    public function canAccessSystem(): bool
    {
        // Suspended accounts regain access once the suspension window passes,
        // but a parallel lock must also have expired before granting access.
        if (
            $this->status === UserStatus::SUSPENDED
            && $this->suspended_until !== null
            && $this->suspended_until->isPast()
        ) {
            return !$this->isLocked();
        }

        if (!in_array($this->status, [UserStatus::ACTIVE, UserStatus::SUSPENDED], true)) {
            // inactive, pending, banned — all blocked.
            return false;
        }

        if ($this->status === UserStatus::SUSPENDED) {
            // Still within the suspension window.
            return false;
        }

        // Check the parallel lock flag.
        if ($this->isLocked()) {
            return false;
        }

        return true;
    }

    /**
     * Whether the account is currently locked (regardless of status).
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Whether the account status is active.
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    /**
     * Whether the account is currently suspended (and suspension is still active).
     */
    public function isSuspended(): bool
    {
        return $this->status === UserStatus::SUSPENDED
            && $this->suspended_until !== null
            && $this->suspended_until->isFuture();
    }

    /**
     * Human-readable reason why access is denied, or null if access is allowed.
     */
    public function accessDeniedReason(): ?string
    {
        if ($this->canAccessSystem()) {
            return null;
        }

        return match (true) {
            $this->isLocked() => 'account_locked',
            $this->status === UserStatus::INACTIVE => 'account_inactive',
            $this->status === UserStatus::PENDING => 'account_pending',
            $this->status === UserStatus::BANNED => 'account_banned',
            $this->status === UserStatus::SUSPENDED
            && $this->suspended_until !== null => 'account_suspended_until',
            $this->status === UserStatus::SUSPENDED => 'account_suspended',
            default => 'account_inactive',
        };
    }

    /**
     * Human-readable status label.
     */
    public function statusLabel(): string
    {
        return UserStatus::label($this->status ?? UserStatus::INACTIVE);
    }

    /**
     * Tailwind / CSS colour class for the current status badge.
     */
    public function statusColour(): string
    {
        return UserStatus::colour($this->status ?? UserStatus::INACTIVE);
    }

    // -------------------------------------------------------------------------
    // Avatar
    // -------------------------------------------------------------------------

    /**
     * Returns the user's avatar URL. Checks for a directly-attached media item
     * in the 'avatars' library first; falls back to the Laravolt generated avatar.
     *
     * Result is memoized per instance via once() so repeated calls within the
     * same request (nav bar, sidebar, breadcrumb, etc.) cost nothing after the
     * first resolve.
     */
    public function avatar(): string
    {
        return once(function () {
            $media = $this->firstMedia('avatars');

            if ($media) {
                return $media->url();
            }

            return LaravoltAvatar::create($this->name)->toBase64();
        });
    }

    /**
     * Replace the user's avatar. Detaches any existing avatars-library media
     * from this user before attaching the new one.
     */
    public function setAvatar(Media $media): void
    {
        $existing = $this->directMedia()
            ->whereHas('library', fn($q) => $q->where('handle', 'avatars'))
            ->get();

        foreach ($existing as $old) {
            $this->detachMedia($old);
        }

        $this->attachMedia($media);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Limit query to users whose status is 'active'.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::ACTIVE);
    }

    /**
     * Limit query to users with a specific status value.
     */
    public function scopeWhereStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function oauthTokenFor(string $provider): ?OauthToken
    {
        return $this->oauthTokens()
            ->provider($provider)
            ->active()
            ->orderByDesc('expires_at')
            ->first();
    }

    public function oauthTokens(): HasMany
    {
        return $this->hasMany(OauthToken::class);
    }

    public function oauthToken(): HasOne
    {
        return $this->hasOne(OauthToken::class)->latestOfMany();
    }

    public function entryAuthor(): HasOne
    {
        return $this->hasOne(EntryAuthor::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(UserStatusLog::class)->orderByDesc('created_at');
    }

    // -------------------------------------------------------------------------
    // Other helpers
    // -------------------------------------------------------------------------

    public function isAuthorEligible(): bool
    {
        return $this->entryAuthor?->status === 'active';
    }
}
