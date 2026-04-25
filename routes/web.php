<?php

use App\Http\Controllers\Login;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TemplateController;

use App\Http\Controllers\SiteController;

Route::get('login/{provider}', [Login::class, 'redirectToProvider'])->name('social.login.provider');
Route::get('login/{provider}/callback', [Login::class, 'handleProviderCallback'])->name('social.login.callback');

Route::get('/{uri?}', [SiteController::class, 'show'])
    ->where('uri', '.*')
    ->name('site.show');
