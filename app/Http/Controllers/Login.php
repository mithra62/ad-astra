<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Socialite;
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
            $user = Socialite::driver($provider)->user();
        } catch (InvalidStateException $e) {
            echo "broken";
            exit;
        }

        print_r($user);
        exit;


        // Find user by email or create a new user
        $localUser = User::firstOrCreate(
            ['email' => $user->getEmail()],
            ['name' => $user->getName()]
        );

        Auth::login($localUser, true); // Login the user

        return redirect($this->redirectTo); // Redirect the user after successful login
    }
}
