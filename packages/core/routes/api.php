<?php

use AdAstra\Http\Controllers\Api\v1\Account;
use AdAstra\Http\Controllers\Api\v1\Categories;
use AdAstra\Http\Controllers\Api\v1\CategoryGroups;
use AdAstra\Http\Controllers\Api\v1\Entries;
use AdAstra\Http\Controllers\Api\v1\EntryGroups;
use AdAstra\Http\Controllers\Api\v1\Statuses;
use AdAstra\Http\Controllers\Api\v1\StatusGroups;
use AdAstra\Http\Controllers\Api\v1\User;
use AdAstra\Http\Middleware\LogRequestResponse;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    Route::apiResource('users', User::class, ['names' => 'api.v1.users'])
        ->middleware(LogRequestResponse::class);

    // Entry Groups + nested Entries
    // Parameter names aligned with existing admin FormRequests:
    //   {group}    -> matches EditEntryGroupRequest::route()->parameter('group')
    //   {group_id} -> matches StoreEntryRequest::route()->parameter('group_id')
    //   {entry}    -> matches EditEntryRequest::route()->parameter('entry')
    Route::apiResource('entry-groups', EntryGroups::class, ['names' => 'api.v1.entry-groups'])
        ->parameters(['entry-groups' => 'group'])
        ->middleware(LogRequestResponse::class);

    Route::apiResource('entry-groups.entries', Entries::class, ['names' => 'api.v1.entry-groups.entries'])
        ->parameters(['entry-groups' => 'group_id', 'entries' => 'entry'])
        ->middleware(LogRequestResponse::class);

    Route::get('/account', [Account::class, 'show'])
        ->middleware(LogRequestResponse::class)
        ->name('api.v1.account.show');

    Route::put('/account', [Account::class, 'update'])
        ->middleware(LogRequestResponse::class)
        ->name('api.v1.account.update');

    Route::put('/account/password', [Account::class, 'updatePassword'])
        ->middleware(LogRequestResponse::class)
        ->name('api.v1.account.password');

    Route::put('/account/email', [Account::class, 'updateEmail'])
        ->middleware(LogRequestResponse::class)
        ->name('api.v1.account.email');

    // Category Groups + nested Categories
    // Parameter names are intentionally aligned with the existing admin FormRequests:
    //   {group}    -> matches EditCategoryGroupRequest::route()->parameter('group')
    //   {group_id} -> matches StoreCategoryRequest::route()->parameter('group_id')
    //   {category} -> matches EditCategoryRequest::route()->parameter('category')
    Route::apiResource('category-groups', CategoryGroups::class, ['names' => 'api.v1.category-groups'])
        ->parameters(['category-groups' => 'group'])
        ->middleware(LogRequestResponse::class);

    Route::apiResource('category-groups.categories', Categories::class, ['names' => 'api.v1.category-groups.categories'])
        ->parameters(['category-groups' => 'group_id', 'categories' => 'category'])
        ->middleware(LogRequestResponse::class);

    // Status Groups + flat Statuses
    // Parameter names aligned with existing admin FormRequests:
    //   {group}  -> matches EditStatusGroupRequest::route('group')
    //   {status} -> matches EditStatusRequest::route('status')
    Route::apiResource('status-groups', StatusGroups::class, ['names' => 'api.v1.status-groups'])
        ->parameters(['status-groups' => 'group'])
        ->middleware(LogRequestResponse::class);

    Route::apiResource('statuses', Statuses::class, ['names' => 'api.v1.statuses'])
        ->middleware(LogRequestResponse::class);
});
