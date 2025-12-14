<?php

namespace App\Providers;

use App\Craft\Client;
use App\Settings;
use App\Rest\Api;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('craft-client', function ($app) {
            return new Client($app);
        });

        $this->app->singleton('settings', function ($app) {
            return new Settings($app);
        });

        $this->app->singleton('api', function ($app) {
            return new Api($app);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super admin') ? true : null;
        });
    }
}
