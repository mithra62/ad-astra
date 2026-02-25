<?php

use App\Http\Controllers\Admin\Account;
use App\Http\Controllers\Admin\Account\Token as AccountTokens;
use App\Http\Controllers\Admin\Category;
use App\Http\Controllers\Admin\Category\Group as CategoryGroup;
use App\Http\Controllers\Admin\Dashboard;
use App\Http\Controllers\Admin\Field;
use App\Http\Controllers\Admin\Field\Group as FieldGroup;
use App\Http\Controllers\Admin\Media\Library;
use App\Http\Controllers\Admin\Media;
use App\Http\Controllers\Admin\Role;
use App\Http\Controllers\Admin\User;
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

    Route::post('media/libraries/{library_id}/upload', [Library::class, 'upload'])->name('media.libraries.upload');
    Route::get('media/libraries/{id}/confirm', [Library::class, 'confirm'])->name('media.libraries.confirm');
    Route::resource('media/libraries', Library::class)
        ->name('index', 'media.libraries')
        ->name('create', 'media.libraries.create')
        ->name('store', 'media.libraries.store')
        ->name('show', 'media.libraries.show')
        ->name('edit', 'media.libraries.edit')
        ->name('update', 'media.libraries.update')
        ->name('destroy', 'media.libraries.destroy');

    Route::get('media/{id}/download', [Media::class, 'download'])->name('media.download');
    Route::get('media/{id}/confirm', [Media::class, 'confirm'])->name('media.confirm');
    Route::get('media/{library_id}/create', [Media::class, 'create'])->name('media.create');
    Route::post('media/{library_id}/create', [Media::class, 'store'])->name('media.store');
    Route::resource('media', Media::class);

    Route::get('categories/{group_id}/create', [Category::class, 'create'])->name('categories.create');
    Route::post('categories/{group_id}/create', [Category::class, 'store'])->name('categories.store');
    Route::get('categories/{id}/confirm', [Category::class, 'confirm'])->name('categories.confirm');
    Route::resource('categories', Category::class);

    Route::get('fields/groups/{id}/confirm', [FieldGroup::class, 'confirm'])->name('fields.groups.confirm');
    Route::resource('fields/groups', FieldGroup::class)
        ->name('index', 'fields.groups')
        ->name('create', 'fields.groups.create')
        ->name('store', 'fields.groups.store')
        ->name('show', 'fields.groups.show')
        ->name('edit', 'fields.groups.edit')
        ->name('update', 'fields.groups.update')
        ->name('destroy', 'fields.groups.destroy');

    Route::get('fields/{group_id}/create', [Field::class, 'create'])->name('fields.create');
    Route::post('fields/{group_id}/create', [Field::class, 'store'])->name('fields.store');
    Route::get('fields/{id}/confirm', [Field::class, 'confirm'])->name('fields.confirm');
    Route::resource('fields', Field::class);

    //dashboard
    Route::get('/dashboard', [Dashboard::class, 'index'])->name('dashboard');
    Route::get('/dashboard/chart', [Dashboard::class, 'chart'])->name('dashboard-chart');

    //categories
});
