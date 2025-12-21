<?php

use App\Http\Controllers\Admin\Account;
use App\Http\Controllers\Admin\Account\Token as AccountTokens;
use App\Http\Controllers\Admin\Dashboard;
use App\Http\Controllers\Admin\Role;
use App\Http\Controllers\Admin\User;
use App\Http\Controllers\Admin\Category;
use App\Http\Controllers\Admin\Category\Group AS CategoryGroup;
use App\Http\Controllers\Admin\User\Token as UserTokens;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth'])->group(function () {

    //users
    Route::get('users/{id}/confirm', [User::class, 'confirm'])->name('users.confirm');
    Route::put('users/{id}/password', [User::class, 'password'])->name('users.password');
    Route::resource('users', User::class);
    Route::get('users/{id}/tokens/create', [UserTokens::class, 'create'])->name('users.token.create');
    Route::post('users/{id}/tokens', [UserTokens::class, 'store'])->name('users.token.store');
    Route::get('users/{id}/tokens/{token_id}/confirm', [UserTokens::class, 'confirm'])->name('users.token.confirm');
    Route::delete('users/{id}/tokens/{token_id}', [UserTokens::class, 'destroy'])->name('users.token.destroy');
    Route::get('users/{id}/tokens/{token_id}/edit', [UserTokens::class, 'edit'])->name('users.token.edit');
    Route::put('users/{id}/tokens/{token_id}', [UserTokens::class, 'update'])->name('users.token.update');

    //roles
    Route::get('roles/{id}/confirm', [Role::class, 'confirm'])->name('roles.confirm');
    Route::resource('roles', Role::class);

    //account
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

    Route::get('categories/groups/{id}/confirm', [CategoryGroup::class, 'confirm'])->name('categories.groups.confirm');
    Route::resource('categories/groups', CategoryGroup::class)
        ->name('index', 'categories.groups')
        ->name('create', 'categories.groups.create')
        ->name('store', 'categories.groups.store')
        ->name('show', 'categories.groups.show')
        ->name('edit', 'categories.groups.edit')
        ->name('update', 'categories.groups.update')
        ->name('destroy', 'categories.groups.destroy');

    Route::get('categories/{group_id}/create', [Category::class, 'create'])->name('categories.create');
    Route::resource('categories', Category::class);

    //dashboard
    Route::get('/dashboard', [Dashboard::class, 'index'])->name('dashboard');
    Route::get('/dashboard/chart', [Dashboard::class, 'chart'])->name('dashboard-chart');

    //categories
});
