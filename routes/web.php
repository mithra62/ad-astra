<?php

use Illuminate\Support\Facades\Route;
use mithra62\Shop\Http\Controllers\Admin\Account;
use mithra62\Shop\Http\Controllers\Admin\Account\Token as AccountTokens;
use mithra62\Shop\Http\Controllers\Admin\Dashboard;
use mithra62\Shop\Http\Controllers\Admin\Index;
use mithra62\Shop\Http\Controllers\Admin\Login;
use mithra62\Shop\Http\Controllers\Admin\Role;
use mithra62\Shop\Http\Controllers\Admin\User;
use mithra62\Shop\Http\Controllers\Admin\User\Token as UserTokens;

Route::get('/', [Index::class, 'index'])->name('home');

Route::get('login/{provider}', [Login::class, 'redirectToProvider'])->name('social.login.provider');
Route::get('login/{provider}/callback', [Login::class,  'handleProviderCallback'])->name('social.login.callback');

Route::middleware(['auth'])->group(function () {

    Route::get('admin', [Index::class, 'index'])->name('home');
    Route::get('admin/users/{id}/confirm', [User::class, 'confirm'])->name('users.confirm');
    Route::put('admin/users/{id}/password', [User::class, 'password'])->name('users.password');
    Route::resource('admin/users', User::class);

    Route::get('admin/users/{id}/tokens/create', [UserTokens::class, 'create'])->name('users.token.create');
    Route::post('admin/users/{id}/tokens', [UserTokens::class, 'store'])->name('users.token.store');

    Route::get('admin/users/{id}/tokens/{token_id}/confirm', [UserTokens::class, 'confirm'])->name('users.token.confirm');
    Route::delete('admin/users/{id}/tokens/{token_id}', [UserTokens::class, 'destroy'])->name('users.token.destroy');

    Route::get('admin/users/{id}/tokens/{token_id}/edit', [UserTokens::class, 'edit'])->name('users.token.edit');
    Route::put('admin/users/{id}/tokens/{token_id}', [UserTokens::class, 'update'])->name('users.token.update');

    Route::get('admin/roles/{id}/confirm', [Role::class, 'confirm'])->name('roles.confirm');
    Route::resource('admin/roles', Role::class);

    Route::get('admin/account', [Account::class, 'index'])->name('account');
    Route::get('admin/account/settings', [Account::class, 'settings'])->name('account.settings');
    Route::put('admin/account', [Account::class, 'update'])->name('account.edit');
    Route::put('admin/account/password', [Account::class, 'change_password'])->name('account.password.update');
    Route::get('admin/account/password', [Account::class, 'password'])->name('account.password');

    Route::get('admin/account/tokens', [AccountTokens::class, 'index'])->name('account.tokens.index');
    Route::get('admin/account/tokens/create', [AccountTokens::class, 'create'])->name('account.tokens.create');
    Route::post('admin/account/tokens', [AccountTokens::class, 'store'])->name('account.tokens.store');
    Route::get('admin/account/tokens/{token_id}/confirm', [AccountTokens::class, 'confirm'])->name('account.tokens.confirm');
    Route::delete('admin/account/tokens/{token_id}', [AccountTokens::class, 'destroy'])->name('account.tokens.destroy');

    Route::get('admin/account/tokens/{token_id}/edit', [AccountTokens::class, 'edit'])->name('account.tokens.edit');
    Route::put('admin/account/tokens/{token_id}', [AccountTokens::class, 'update'])->name('account.tokens.update');

    Route::get('admin/dashboard', [Dashboard::class, 'index'])->name('dashboard');
    Route::get('admin/dashboard/chart', [Dashboard::class, 'chart'])->name('dashboard-chart');
});
