<?php

namespace AdAstra\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use AdAstra\Enums\UserStatus;
use AdAstra\Models\User\OauthToken;
use AdAstra\Support\UserFieldLayout;
use AdAstra\Traits\Field\Fieldable;
use AdAstra\Traits\HasMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravolt\Avatar\Facade as LaravoltAvatar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use HasApiTokens;
    use HasRoles;
    use Fieldable;
    use TwoFactorAuthenticatable;
    use HasMedia;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'suspended_until' => 'datetime',
        'banned_at' => 'datetime',
        'locked_until' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::SUSPENDED
            && $this->suspended_until !== null
            && $this->suspended_until->isFuture();
    }

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

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

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

    public function avatar(): string
    {
        return once(function () {
            $media = $this->firstMedia('avatars');

            if ($media) {
                return $media->url();
            }

            return LaravoltAvatar::create($this->email)->toBase64();
        });
    }

    /**
     * Intended field schema for a user: the layout configured under
     * Settings users.user_field_layout_id.
     */
    public function fieldSchema(): Collection
    {
        return UserFieldLayout::resolve()?->fields() ?? collect();
    }

    public function setAvatar(Media $media): void
    {
        $existing = $this->directMedia()
            ->whereHas('library', fn ($q) => $q->where('handle', 'avatars'))
            ->get();

        foreach ($existing as $old) {
            $this->detachMedia($old);
        }

        $this->attachMedia($media);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::ACTIVE);
    }

    public function scopeWhereStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

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

    public function isAuthorEligible(): bool
    {
        return $this->entryAuthor?->status === 'active';
    }
}
