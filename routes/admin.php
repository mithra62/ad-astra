<?php

use App\Http\Controllers\Admin\Account;
use App\Http\Controllers\Admin\Account\Token as AccountTokens;
use App\Http\Controllers\Admin\Category;
use App\Http\Controllers\Admin\Category\Group as CategoryGroup;
use App\Http\Controllers\Admin\Dashboard;
use App\Http\Controllers\Admin\Entry;
use App\Http\Controllers\Admin\Entry\Group as EntryGroup;
use App\Http\Controllers\Admin\Entry\Type as EntryType;
use App\Http\Controllers\Admin\Field;
use App\Http\Controllers\Admin\Field\Group as FieldGroup;
use App\Http\Controllers\Admin\FieldLayout as FieldLayoutController;
use App\Http\Controllers\Admin\FieldLayout\Tab as FieldLayoutTab;
use App\Http\Controllers\Admin\FieldLayout\TabElement as FieldLayoutTabElement;
use App\Http\Controllers\Admin\Media\Library;
use App\Http\Controllers\Admin\Media;
use App\Http\Controllers\Admin\Role;
use App\Http\Controllers\Admin\Status;
use App\Http\Controllers\Admin\Status\Group as StatusGroup;
use App\Http\Controllers\Admin\User;
use App\Http\Controllers\Admin\User\Token as UserTokens;
use App\Http\Controllers\Admin\User\Layout AS UserLayout;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth'])->group(function () {

    //users
    Route::get('users/{id}/confirm', [User::class, 'confirm'])->name('users.confirm');
    Route::put('users/{id}/password', [User::class, 'password'])->name('users.password');
    Route::get('users/layouts', [UserLayout::class, 'show'])->name('users.layouts.show');
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

    //statuses
    Route::get('statuses/groups/{id}/confirm', [StatusGroup::class, 'confirm'])->name('statuses.groups.confirm');
    Route::resource('statuses/groups', StatusGroup::class)
        ->name('index', 'statuses.groups')
        ->name('create', 'statuses.groups.create')
        ->name('store', 'statuses.groups.store')
        ->name('show', 'statuses.groups.show')
        ->name('edit', 'statuses.groups.edit')
        ->name('update', 'statuses.groups.update')
        ->name('destroy', 'statuses.groups.destroy');
    Route::get('statuses/{group_id}/create', [Status::class, 'create'])->name('statuses.create');
    Route::post('statuses/{group_id}/create', [Status::class, 'store'])->name('statuses.store');
    Route::get('statuses/{id}/confirm', [Status::class, 'confirm'])->name('statuses.confirm');
    Route::resource('statuses', Status::class)->except(['index', 'create', 'store']);

    //entries
    Route::get('entries/groups', [EntryGroup::class, 'index'])->name('entries.groups');
    Route::get('entries/groups/create', [EntryGroup::class, 'create'])->name('entries.groups.create');
    Route::post('entries/groups', [EntryGroup::class, 'store'])->name('entries.groups.store');
    Route::get('entries/groups/{id}/edit', [EntryGroup::class, 'edit'])->name('entries.groups.edit');
    Route::put('entries/groups/{id}', [EntryGroup::class, 'update'])->name('entries.groups.update');
    Route::delete('entries/groups/{id}', [EntryGroup::class, 'destroy'])->name('entries.groups.destroy');
    Route::get('entries/groups/{id}/confirm', [EntryGroup::class, 'confirm'])->name('entries.groups.confirm');
    Route::get('entries/groups/{id}', [EntryGroup::class, 'show'])->name('entries.groups.show');
    // Entry Types within a group
    Route::get('entries/groups/{group_id}/types/create', [EntryType::class, 'create'])->name('entries.groups.types.create');
    Route::post('entries/groups/{group_id}/types', [EntryType::class, 'store'])->name('entries.groups.types.store');
    Route::get('entries/groups/{group_id}/types/{type_id}/edit', [EntryType::class, 'edit'])->name('entries.groups.types.edit');
    Route::put('entries/groups/{group_id}/types/{type_id}', [EntryType::class, 'update'])->name('entries.groups.types.update');
    Route::get('entries/groups/{group_id}/types/{type_id}/confirm', [EntryType::class, 'confirm'])->name('entries.groups.types.confirm');
    Route::delete('entries/groups/{group_id}/types/{type_id}', [EntryType::class, 'destroy'])->name('entries.groups.types.destroy');
    // Entry CRUD
    Route::get('entries/groups/{group_id}/create', [Entry::class, 'create'])->name('entries.create');
    Route::post('entries/groups/{group_id}/create', [Entry::class, 'store'])->name('entries.store');
    Route::get('entries/{id}/confirm', [Entry::class, 'confirm'])->name('entries.confirm');
    Route::resource('entries', Entry::class)->only(['edit', 'update', 'destroy']);

    // Field Layouts
    Route::get('field-layouts', [FieldLayoutController::class, 'index'])->name('field-layouts');
    Route::get('field-layouts/create', [FieldLayoutController::class, 'create'])->name('field-layouts.create');
    Route::post('field-layouts', [FieldLayoutController::class, 'store'])->name('field-layouts.store');
    Route::get('field-layouts/{id}/edit', [FieldLayoutController::class, 'edit'])->name('field-layouts.edit');
    Route::put('field-layouts/{id}', [FieldLayoutController::class, 'update'])->name('field-layouts.update');
    Route::get('field-layouts/{id}/confirm', [FieldLayoutController::class, 'confirm'])->name('field-layouts.confirm');
    Route::delete('field-layouts/{id}', [FieldLayoutController::class, 'destroy'])->name('field-layouts.destroy');
    // Tabs within a layout
    Route::get('field-layouts/{layout_id}/tabs/create', [FieldLayoutTab::class, 'create'])->name('field-layouts.tabs.create');
    Route::post('field-layouts/{layout_id}/tabs', [FieldLayoutTab::class, 'store'])->name('field-layouts.tabs.store');
    Route::get('field-layouts/{layout_id}/tabs/{tab_id}/edit', [FieldLayoutTab::class, 'edit'])->name('field-layouts.tabs.edit');
    Route::put('field-layouts/{layout_id}/tabs/{tab_id}', [FieldLayoutTab::class, 'update'])->name('field-layouts.tabs.update');
    Route::get('field-layouts/{layout_id}/tabs/{tab_id}/confirm', [FieldLayoutTab::class, 'confirm'])->name('field-layouts.tabs.confirm');
    Route::delete('field-layouts/{layout_id}/tabs/{tab_id}', [FieldLayoutTab::class, 'destroy'])->name('field-layouts.tabs.destroy');
    // Elements within a tab
    Route::post('field-layouts/{layout_id}/tabs/{tab_id}/elements', [FieldLayoutTabElement::class, 'store'])->name('field-layouts.tabs.elements.store');
    Route::put('field-layouts/{layout_id}/tabs/{tab_id}/elements/{element_id}', [FieldLayoutTabElement::class, 'update'])->name('field-layouts.tabs.elements.update');
    Route::get('field-layouts/{layout_id}/tabs/{tab_id}/elements/{element_id}/confirm', [FieldLayoutTabElement::class, 'confirm'])->name('field-layouts.tabs.elements.confirm');
    Route::delete('field-layouts/{layout_id}/tabs/{tab_id}/elements/{element_id}', [FieldLayoutTabElement::class, 'destroy'])->name('field-layouts.tabs.elements.destroy');

    //dashboard
    Route::get('/dashboard', [Dashboard::class, 'index'])->name('dashboard');
    Route::get('/dashboard/chart', [Dashboard::class, 'chart'])->name('dashboard-chart');

    //categories
});
