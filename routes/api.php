<?php

use App\Http\Controllers\Api\v1\User;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\LogRequestResponse;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::apiResource('users', User::class, ['names' => 'api.v1.users'])
        ->middleware(LogRequestResponse::class);
});
