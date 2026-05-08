<?php

namespace App\Http\Controllers;

use App\Facades\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class Login extends Controller
{
    public function redirectToProvider(string $provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback(Request $request, string $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException $e) {
            return redirect()->route('login')
                ->withErrors(['oauth' => __('auth.oauth_state_invalid')]);
        }

        // Find user by email or create a new one for this social provider.
        // The provider name and IP are passed so a NewSocialUserRegistered event
        // can be fired for new accounts.
        $localUser = Users::firstOrCreateFromSocial(
            $socialUser->getEmail(),
            $socialUser->getName(),
            $provider,
            $request->ip(),
        );

        // Block access if the account is not permitted to use the system.
        if (! $localUser->canAccessSystem()) {
            return redirect()->route('login')
                ->withErrors(['email' => trans('auth.' . ($localUser->accessDeniedReason() ?? 'account_inactive'))]);
        }

        Auth::login($localUser, true);

        return redirect($this->redirectTo ?? '/');
    }
}
