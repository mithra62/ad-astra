<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use App\Models\User\OauthToken;
use App\Traits\Fieldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravolt\Avatar\Avatar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, Fieldable, TwoFactorAuthenticatable;

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

    // -------------------------------------------------------------------------
    // Access helpers
    // -------------------------------------------------------------------------

    /**
     * Whether this user is permitted to access the system right now.
     *
     * Checks both the administrative status and the parallel lock flag.
     * Auto-expiry for suspended_until and locked_until is handled here at
     * runtime — no cron required.
     */
    public function canAccessSystem(): bool
    {
        // Suspended accounts regain access once the suspension window passes.
        if (
            $this->status === UserStatus::SUSPENDED
            && $this->suspended_until !== null
            && $this->suspended_until->isPast()
        ) {
            // Treat as active for access purposes; UserService will clean up
            // the column on next explicit status change.
            return true;
        }

        if (! in_array($this->status, [UserStatus::ACTIVE, UserStatus::SUSPENDED], true)) {
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

        if ($this->isLocked()) {
            return 'account_locked';
        }

        return match ($this->status) {
            UserStatus::INACTIVE  => 'account_inactive',
            UserStatus::PENDING   => 'account_pending',
            UserStatus::SUSPENDED => 'account_suspended',
            UserStatus::BANNED    => 'account_banned',
            default               => 'account_inactive',
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

    /**
     * Generates an avatar for the user based on their email using Gravatar service.
     */
    public function avatar(): string
    {
        $return = '';
        if ($this->email) {
            $return = app(Avatar::class)->create($this->email)->toGravatar();
        }

        return $return;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'suspended_until'   => 'datetime',
            'banned_at'         => 'datetime',
            'locked_until'      => 'datetime',
        ];
    }
}
