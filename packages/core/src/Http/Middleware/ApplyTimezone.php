<?php

namespace AdAstra\Http\Middleware;

use AdAstra\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyTimezone
{
    public function __construct(private Settings $settings)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tz = $this->settings->get('general', 'timezone', 'UTC', $request->user());

        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);

        return $next($request);
    }
}
