<?php

namespace AdAstra\Actions\User;

use AdAstra\Actions\AbstractAction;
use AdAstra\Facades\Users as UsersFacade;
use AdAstra\Models\User;
use AdAstra\Traits\PasswordValidationRules;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser extends AbstractAction implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        return UsersFacade::create($input);
    }
}
