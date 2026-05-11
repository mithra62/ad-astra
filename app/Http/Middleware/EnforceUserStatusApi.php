<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceUserStatusApi
{
    /**
     * Block API requests from users whose status no longer permits access.
     *
     * Always returns a 403 JSON response — session teardown is the web
     * middleware's responsibility. SPA clients receive the 403 and handle
     * the redirect to login in the frontend.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->canAccessSystem()) {
            return response()->json(['message' => __('auth.account_inactive')], 403);
        }

        return $next($request);
    }
}
