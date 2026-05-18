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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Enforce account status on every authenticated web request.
        $middleware->web(append: [
            \App\Http\Middleware\EnforceUserStatus::class,
            \App\Http\Middleware\ApplyTimezone::class,
        ]);

        // Enforce account status on every authenticated API request.
        $middleware->api(append: [
            \App\Http\Middleware\EnforceUserStatusApi::class,
            \App\Http\Middleware\ApplyTimezone::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
