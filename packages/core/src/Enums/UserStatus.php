<?php

namespace AdAstra\Enums;

class UserStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const PENDING = 'pending';
    public const SUSPENDED = 'suspended';
    public const BANNED = 'banned';

    /**
     * All valid status values.
     * Use with Rule::in(UserStatus::ALL).
     */
    public const ALL = [
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
    public const CREATION_ALLOWED = [
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
