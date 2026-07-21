<?php

namespace AdAstra\Events;

use AdAstra\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserLockChanged
{
    use Dispatchable;

    public function __construct(
        public readonly User   $user,
        public readonly mixed  $previousLockedUntil,
        public readonly mixed  $newLockedUntil,
        public readonly string $reason = 'admin',
    ) {
    }
}
