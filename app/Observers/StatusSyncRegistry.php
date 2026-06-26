<?php

namespace App\Observers;

/**
 * Registry of models that carry a denormalized Status triple
 * (status_id / status_handle / status_is_public).
 *
 * Consumers self-register via the HasStatus trait's bootHasStatus() hook.
 * StatusObserver iterates the registry to cascade is_public / handle changes
 * across every consumer that points at the mutated Status row.
 *
 * @see \App\Traits\HasStatus
 * @see \App\Observers\StatusObserver
 */
class StatusSyncRegistry
{
    /** @var array<class-string, class-string> */
    private static array $consumers = [];

    public static function register(string $modelClass): void
    {
        self::$consumers[$modelClass] = $modelClass;
    }

    /**
     * @return array<int, class-string>
     */
    public static function consumers(): array
    {
        return array_values(self::$consumers);
    }

    public static function clear(): void
    {
        self::$consumers = [];
    }
}
