<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Web/admin/api routes are registered by AdAstra\Providers\AppServiceProvider
        // (from packages/core/routes/*). Only the console routes file — which defines
        // the schedule and the `inspire` command — is wired here; the framework command
        // classes are registered by the package provider.
        commands: __DIR__ . '/../packages/core/routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Enforce account status on every authenticated web request.
        $middleware->web(append: [
            \AdAstra\Http\Middleware\EnforceUserStatus::class,
            \AdAstra\Http\Middleware\ApplyTimezone::class,
        ]);

        // Enforce account status on every authenticated API request.
        $middleware->api(append: [
            \AdAstra\Http\Middleware\EnforceUserStatusApi::class,
            \AdAstra\Http\Middleware\ApplyTimezone::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
