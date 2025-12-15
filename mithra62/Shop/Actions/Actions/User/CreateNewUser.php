<?php

namespace mithra62\Shop\Actions\Actions\User;

use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use mithra62\Shop\Models\User;

class CreateNewUser implements CreatesNewUsers
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
