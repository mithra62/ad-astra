<?php

namespace App\Actions\User\Token;

use App\Models\User;
use Carbon\Carbon;
use App\Actions\AbstractAction;

class CreateNewUserToken extends AbstractAction
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
