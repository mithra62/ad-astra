<?php

use App\Http\Controllers\Api\v1\Account;
use App\Http\Controllers\Api\v1\Entries;
use App\Http\Controllers\Api\v1\User;
use App\Http\Middleware\LogRequestResponse;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    Route::apiResource('users', User::class, ['names' => 'api.v1.users'])
        ->middleware(LogRequestResponse::class);

    Route::apiResource('entries', Entries::class, ['names' => 'api.v1.entries'])
        ->middleware(LogRequestResponse::class);

    Route::get('/account', [Account::class, 'show'])
        ->middleware(LogRequestResponse::class)
        ->name('api.v1.account.show');
});
