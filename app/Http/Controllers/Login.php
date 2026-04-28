<?php

namespace App\Http\Controllers;

use App\Facades\Users;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class Login extends Controller
{
    public function redirectToProvider($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException $e) {
            echo "broken";
            exit;
        }

        // Find user by email or create a new one for this social provider
        $localUser = Users::firstOrCreateFromSocial(
            $socialUser->getEmail(),
            $socialUser->getName(),
        );

        Auth::login($localUser, true);

        return redirect($this->redirectTo);
    }
}
