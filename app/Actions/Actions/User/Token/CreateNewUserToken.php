<?php

namespace App\Actions\Actions\User\Token;

use App\Models\User;
use Carbon\Carbon;

class CreateNewUserToken
{
    public function create(User $user, array $input)
    {
        $expires = null;
        if(!empty($input['expires_at'])) {
            $expires = new Carbon($input['expires_at']);
        }

        return $user->createToken($input['name'], ['*'], $expires);
    }
}
