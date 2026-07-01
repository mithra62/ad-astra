<?php

namespace AdAstra\Actions\User\Token;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\User;
use AdAstra\Services\UserService;
use Carbon\Carbon;
use Laravel\Sanctum\NewAccessToken;

/**
 * @deprecated  Delegate directly to \AdAstra\Services\UserService::createToken()
 *              or the \AdAstra\Facades\Users facade.
 */
class CreateNewUserToken extends AbstractAction
{
    public function create(User $user, array $input): NewAccessToken
    {
        $expires = null;
        if (!empty($input['expires_at'])) {
            $expires = new Carbon($input['expires_at']);
        }

        return app(UserService::class)->createToken($user, $input['name'], ['*'], $expires);
    }
}
