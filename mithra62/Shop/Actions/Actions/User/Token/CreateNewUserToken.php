<?php

namespace mithra62\Shop\Actions\Actions\User\Token;

use Carbon\Carbon;
use mithra62\Shop\Models\User;

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
