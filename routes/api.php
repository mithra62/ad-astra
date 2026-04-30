<?php

use App\Http\Controllers\Api\v1\Account;
use App\Http\Controllers\Api\v1\Categories;
use App\Http\Controllers\Api\v1\CategoryGroups;
use App\Http\Controllers\Api\v1\Entries;
use App\Http\Controllers\Api\v1\Statuses;
use App\Http\Controllers\Api\v1\StatusGroups;
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

    // Category Groups + nested Categories
    // Parameter names are intentionally aligned with the existing admin FormRequests:
    //   {group}    → matches EditCategoryGroupRequest::route()->parameter('group')
    //   {group_id} → matches StoreCategoryRequest::route()->parameter('group_id')
    //   {category} → matches EditCategoryRequest::route()->parameter('category')
    Route::apiResource('category-groups', CategoryGroups::class, ['names' => 'api.v1.category-groups'])
        ->parameters(['category-groups' => 'group'])
        ->middleware(LogRequestResponse::class);

    Route::apiResource('category-groups.categories', Categories::class, ['names' => 'api.v1.category-groups.categories'])
        ->parameters(['category-groups' => 'group_id', 'categories' => 'category'])
        ->middleware(LogRequestResponse::class);

    // Status Groups + flat Statuses
    // Parameter names aligned with existing admin FormRequests:
    //   {group}  → matches EditStatusGroupRequest::route('group')
    //   {status} → matches EditStatusRequest::route('status')
    Route::apiResource('status-groups', StatusGroups::class, ['names' => 'api.v1.status-groups'])
        ->parameters(['status-groups' => 'group'])
        ->middleware(LogRequestResponse::class);

    Route::apiResource('statuses', Statuses::class, ['names' => 'api.v1.statuses'])
        ->middleware(LogRequestResponse::class);
});
