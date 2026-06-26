<?php

namespace App\Actions\User;

use App\Actions\AbstractAction;
use App\Models\User;
use App\Services\UserService;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword extends AbstractAction implements UpdatesUserPasswords
{
    /**
     * Validate and update the user's password.
     *
     * @param array<string, string> $input
     */
    public function update(User $user, array $input): void
    {
        app(UserService::class)->setPassword($user, $input['password']);
    }
}
