<?php

use Illuminate\Support\Facades\Route;
use mithra62\Shop\Http\Controllers\Admin\Account;
use mithra62\Shop\Http\Controllers\Admin\Account\Token as AccountTokens;
use mithra62\Shop\Http\Controllers\Admin\Dashboard;
use mithra62\Shop\Http\Controllers\Admin\Index;
use mithra62\Shop\Http\Controllers\Admin\Login;
use mithra62\Shop\Http\Controllers\Admin\Playground;
use mithra62\Shop\Http\Controllers\Admin\Role;
use mithra62\Shop\Http\Controllers\Admin\User;
use mithra62\Shop\Http\Controllers\Admin\User\Token as UserTokens;

Route::get('/', [Index::class, 'index'])->name('home');

Route::get('login/{provider}', [Login::class, 'redirectToProvider'])->name('social.login.provider');
Route::get('login/{provider}/callback', [Login::class,  'handleProviderCallback'])->name('social.login.callback');

Route::middleware(['auth'])->group(function () {

    Route::get('users/{id}/confirm', [User::class, 'confirm'])->name('users.confirm');
    Route::put('users/{id}/password', [User::class, 'password'])->name('users.password');
    Route::resource('users', User::class);

    Route::get('users/{id}/tokens/create', [UserTokens::class, 'create'])->name('users.token.create');
    Route::post('users/{id}/tokens', [UserTokens::class, 'store'])->name('users.token.store');

    Route::get('users/{id}/tokens/{token_id}/confirm', [UserTokens::class, 'confirm'])->name('users.token.confirm');
    Route::delete('users/{id}/tokens/{token_id}', [UserTokens::class, 'destroy'])->name('users.token.destroy');

    Route::get('users/{id}/tokens/{token_id}/edit', [UserTokens::class, 'edit'])->name('users.token.edit');
    Route::put('users/{id}/tokens/{token_id}', [UserTokens::class, 'update'])->name('users.token.update');

    Route::get('roles/{id}/confirm', [Role::class, 'confirm'])->name('roles.confirm');
    Route::resource('roles', Role::class);

    Route::get('/account', [Account::class, 'index'])->name('account');
    Route::get('/account/settings', [Account::class, 'settings'])->name('account.settings');
    Route::put('/account', [Account::class, 'update'])->name('account.edit');
    Route::put('/account/password', [Account::class, 'change_password'])->name('account.password.update');
    Route::get('/account/password', [Account::class, 'password'])->name('account.password');

    Route::get('/account/tokens', [AccountTokens::class, 'index'])->name('account.tokens.index');
    Route::get('account/tokens/create', [AccountTokens::class, 'create'])->name('account.tokens.create');
    Route::post('account/tokens', [AccountTokens::class, 'store'])->name('account.tokens.store');
    Route::get('account/tokens/{token_id}/confirm', [AccountTokens::class, 'confirm'])->name('account.tokens.confirm');
    Route::delete('account/tokens/{token_id}', [AccountTokens::class, 'destroy'])->name('account.tokens.destroy');

    Route::get('account/tokens/{token_id}/edit', [AccountTokens::class, 'edit'])->name('account.tokens.edit');
    Route::put('account/tokens/{token_id}', [AccountTokens::class, 'update'])->name('account.tokens.update');

    Route::get('playground', [Playground::class, 'index'])->name('playground');

    Route::get('/dashboard', [Dashboard::class, 'index'])->name('dashboard');
    Route::get('/dashboard/chart', [Dashboard::class, 'chart'])->name('dashboard-chart');
});
