<?php

namespace App\Actions\User;

use App\Actions\AbstractAction;
use App\Models\User;
use App\Services\UserService;
use App\Traits\PasswordValidationRules;
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
