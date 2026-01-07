<?php

use App\Http\Controllers\Admin\Index;
use App\Http\Controllers\Login;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TemplateController;

Route::get('login/{provider}', [Login::class, 'redirectToProvider'])->name('social.login.provider');
Route::get('login/{provider}/callback', [Login::class, 'handleProviderCallback'])->name('social.login.callback');

Route::get('/', [TemplateController::class, 'render'])
    ->defaults('group', 'site')
    ->defaults('template', 'index')->name('home');

// /{group} -> {group}/index
Route::get('{group}', [TemplateController::class, 'renderGroupIndex'])
    ->where('group', '[A-Za-z0-9\-_]+');

// /{group}/{second} -> either {group}/{second} (action template) OR {group}/entry (slug)
Route::get('{group}/{second}', [TemplateController::class, 'renderGroupSecond'])
    ->where('group', '[A-Za-z0-9\-_]+')
    ->where('second', '[A-Za-z0-9\-_]+');

// /{group}/{second}/{third...} -> action templates with tail segments
Route::get('{group}/{template}/{tail?}', [TemplateController::class, 'renderWithTail'])
    ->where('group', '[A-Za-z0-9\-_]+')
    ->where('template', '[A-Za-z0-9\-_]+')
    ->where('tail', '.*');
