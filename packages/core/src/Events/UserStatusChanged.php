<?php

namespace AdAstra\Events;

use AdAstra\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly User    $user,
        public readonly ?string $previousStatus,
        public readonly string  $newStatus,
        public readonly ?string $reason = null,
        public readonly array   $context = [],
    )
    {
    }
}
