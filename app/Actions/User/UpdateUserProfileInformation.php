<?php

namespace App\Actions\User;

use App\Facades\Users as UsersFacade;
use App\Models\User;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use App\Actions\AbstractAction;

class UpdateUserProfileInformation extends AbstractAction implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): User
    {
        return UsersFacade::update($user, $input);
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
