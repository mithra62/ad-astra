<?php

namespace App\Actions\User;

use App\Models\User;
use App\Traits\PasswordValidationRules;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use App\Actions\AbstractAction;

class CreateNewUser extends AbstractAction implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        if (!empty($input['password'])) {
            $input['password'] = Hash::make($input['password']);
        }

        $user = User::create($input);
        if (!empty($input['roles'])) {
            foreach($input['roles'] AS $role) {
                $user->assignRole($role);
            }
        }

        return $user;
    }
}
