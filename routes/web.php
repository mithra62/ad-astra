<?php

use App\Http\Controllers\Admin\Index;
use App\Http\Controllers\Login;
use Illuminate\Support\Facades\Route;

Route::get('/', [Index::class, 'index'])->name('home');

Route::get('login/{provider}', [Login::class, 'redirectToProvider'])->name('social.login.provider');
Route::get('login/{provider}/callback', [Login::class, 'handleProviderCallback'])->name('social.login.callback');
