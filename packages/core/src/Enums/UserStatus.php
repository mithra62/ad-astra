<?php

namespace AdAstra\Enums;

class UserStatus
{
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';
    const PENDING = 'pending';
    const SUSPENDED = 'suspended';
    const BANNED = 'banned';

    /**
     * All valid status values.
     * Use with Rule::in(UserStatus::ALL).
     */
    const ALL = [
        self::ACTIVE,
        self::INACTIVE,
        self::PENDING,
        self::SUSPENDED,
        self::BANNED,
    ];

    /**
     * Values permitted at user creation time.
     * 'suspended' and 'banned' are post-creation actions only.
     */
    const CREATION_ALLOWED = [
        self::ACTIVE,
        self::INACTIVE,
        self::PENDING,
    ];

    public static function label(string $status): string
    {
        return match ($status) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::PENDING => 'Pending Approval',
            self::SUSPENDED => 'Suspended',
            self::BANNED => 'Banned',
            default => ucfirst($status),
        };
    }

    /**
     * Badge colour helper for Twig/Blade templates.
     * Returns a Tailwind CSS colour token (e.g. 'emerald', 'red').
     */
    public static function colour(string $status): string
    {
        return match ($status) {
            self::ACTIVE => 'emerald',
            self::PENDING => 'amber',
            self::SUSPENDED => 'orange',
            self::INACTIVE => 'slate',
            self::BANNED => 'red',
            default => 'slate',
        };
    }
}
