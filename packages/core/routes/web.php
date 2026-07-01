<?php

use AdAstra\Http\Controllers\Login;
use AdAstra\Http\Controllers\Site;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::get('login/{provider}',          [Login::class, 'redirectToProvider'])->name('social.login.provider');
    Route::get('login/{provider}/callback', [Login::class, 'handleProviderCallback'])->name('social.login.callback');
});

Route::get('/{uri?}', [Site::class, 'show'])
    ->where('uri', '.*')
    ->name('site.show');
