<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__ . '/../routes/admin.php',
            __DIR__ . '/../routes/web.php',
        ],
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    // Framework Artisan commands now live in the AdAstra package. Directory-based
    // discovery maps paths under app/ to the App\ namespace, so it can't resolve these;
    // register them explicitly by class. (Stage 2: the package service provider will
    // register these via $this->commands().)
    ->withCommands([
        \AdAstra\Console\Commands\RefreshTokens::class,
        \AdAstra\Console\Commands\ValidateClassReferences::class,
    ])
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
