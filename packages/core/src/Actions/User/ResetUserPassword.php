<?php

namespace AdAstra\Actions\User;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\User;
use AdAstra\Services\UserService;
use AdAstra\Traits\PasswordValidationRules;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword extends AbstractAction implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param array<string, string> $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        app(UserService::class)->setPassword($user, $input['password']);
    }
}
