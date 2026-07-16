<?php

namespace AdAstra\Providers;

use AdAstra\EntryTypes\EntryTypeRegistry;
use AdAstra\Repositories\EntryRepository;
use AdAstra\Services\ContentService;
use AdAstra\Services\EntryGroupService;
use AdAstra\Services\EntryService;
use AdAstra\Services\EntryTreeService;
use AdAstra\Services\EntryTypeService;
use Illuminate\Support\ServiceProvider;

class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EntryTypeRegistry::class);
        $this->app->singleton(EntryRepository::class);

        $this->app->singleton(EntryTreeService::class, fn ($app) => new EntryTreeService($app));

        // ContentService is the concrete singleton; EntryService resolves to the same instance.
        $this->app->singleton(ContentService::class, function ($app) {
            return new ContentService(
                $app,
                $app->make(EntryTypeRegistry::class),
                $app->make(EntryRepository::class),
                $app->make(EntryTreeService::class),
            );
        });

        $this->app->alias(ContentService::class, EntryService::class);

        $this->app->singleton(EntryGroupService::class, fn ($app) => new EntryGroupService($app));
        $this->app->singleton(EntryTypeService::class, fn ($app) => new EntryTypeService($app));
    }
}
