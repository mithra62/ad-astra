<?php

namespace App\Actions\User;

use App\Actions\AbstractAction;
use App\Models\User;
use App\Rules\MatchCurrentPassword;
use App\Services\UserService;
use App\Traits\PasswordValidationRules;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword extends AbstractAction implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param array<string, string> $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', new MatchCurrentPassword()],
            'password' => $this->passwordRules(),
        ])->validate();

        app(UserService::class)->setPassword($user, $input['password']);
    }
}
