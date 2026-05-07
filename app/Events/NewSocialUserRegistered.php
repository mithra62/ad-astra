<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class NewSocialUserRegistered
{
    use Dispatchable;

    public function __construct(
        public readonly User   $user,
        public readonly string $provider,
        public readonly string $ip,
    )
    {
    }
}
