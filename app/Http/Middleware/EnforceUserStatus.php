<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class EnforceUserStatus
{
    /**
     * Reject already-authenticated users whose status has changed to a
     * blocking value since they last logged in. Logs them out immediately
     * so the session is invalidated rather than just redirected.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->canAccessSystem()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors([Fortify::username() => __('auth.account_inactive')]);
        }

        return $next($request);
    }
}
