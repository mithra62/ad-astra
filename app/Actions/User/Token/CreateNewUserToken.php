<?php

namespace App\Actions\User\Token;

use App\Models\User;
use App\Services\UserService;
use Carbon\Carbon;
use App\Actions\AbstractAction;
use Laravel\Sanctum\NewAccessToken;

/**
 * @deprecated  Delegate directly to \App\Services\UserService::createToken()
 *              or the \App\Facades\Users facade.
 */
class CreateNewUserToken extends AbstractAction
{
    public function create(User $user, array $input): NewAccessToken
    {
        $expires = null;
        if (! empty($input['expires_at'])) {
            $expires = new Carbon($input['expires_at']);
        }

        return app(UserService::class)->createToken($user, $input['name'], ['*'], $expires);
    }
}
