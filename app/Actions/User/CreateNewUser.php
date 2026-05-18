<?php

namespace App\Actions\User;

use App\Actions\AbstractAction;
use App\Facades\Users as UsersFacade;
use App\Models\User;
use App\Traits\PasswordValidationRules;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser extends AbstractAction implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        return UsersFacade::create($input);
    }
}
