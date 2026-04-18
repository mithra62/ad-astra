<?php

namespace App\Providers;

use App\EntryTypes\EntryTypeRegistry;
use App\Repositories\EntryRepository;
use App\Services\ContentService;
use Illuminate\Support\ServiceProvider;

class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EntryTypeRegistry::class);
        $this->app->singleton(EntryRepository::class);

        $this->app->singleton(ContentService::class, function ($app) {
            return new ContentService(
                $app->make(EntryTypeRegistry::class),
                $app->make(EntryRepository::class),
            );
        });
    }
}
