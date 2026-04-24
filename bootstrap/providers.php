<?php

use App\Providers\AppServiceProvider;
use App\Providers\BotBlockServiceProvider;
use App\Providers\ContentServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    BotBlockServiceProvider::class,
    ContentServiceProvider::class,
    FortifyServiceProvider::class,
];
