<?php

namespace App\Actions\User;

use App\Models\User;
use App\Traits\PasswordValidationRules;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use App\Actions\AbstractAction;
use App\Facades\Users AS UsersFacade;

class CreateNewUser extends AbstractAction implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        return UsersFacade::create($input);
    }
}
