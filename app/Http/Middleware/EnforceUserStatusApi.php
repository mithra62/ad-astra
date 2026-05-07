<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class EnforceUserStatusApi
{
    /**
     * Block API requests from users whose status no longer permits access.
     *
     * For JSON / API clients returns 403 with a JSON body.
     * For stateful (web-session) requests that somehow reach this middleware,
     * falls back to the same logout-and-redirect behaviour as EnforceUserStatus
     * so the browser never sees a raw JSON 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->canAccessSystem()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('auth.account_inactive')], 403);
            }

            // Stateful fallback: log out and redirect to login.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors([Fortify::username() => __('auth.account_inactive')]);
        }

        return $next($request);
    }
}
