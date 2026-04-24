<?php

namespace App\Providers;

use App\EntryTypes\EntryTypeRegistry;
use App\Repositories\EntryRepository;
use App\Services\ContentService;
use App\Services\EntryService;
use Illuminate\Support\ServiceProvider;

class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EntryTypeRegistry::class);
        $this->app->singleton(EntryRepository::class);

        // ContentService is the concrete singleton; EntryService resolves to the same instance.
        $this->app->singleton(ContentService::class, function ($app) {
            return new ContentService(
                $app,
                $app->make(EntryTypeRegistry::class),
                $app->make(EntryRepository::class),
            );
        });

        $this->app->alias(ContentService::class, EntryService::class);
    }
}
