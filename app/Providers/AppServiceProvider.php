<?php

namespace App\Providers;

use App\Rest\Api;
use App\Settings;
use App\Services\FilesService;
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
        $this->app->singleton('settings', function ($app) {
            return new Settings($app);
        });

        $this->app->singleton('api', function ($app) {
            return new Api($app);
        });

        $this->app->singleton('files-service', function ($app) {
            return new FilesService($app);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
//        Route::group([
//            'namespace' => 'mithra62\Shop\Http\Controllers',
//            'domain' => config('fortify.domain', null),
//            'prefix' => config('fortify.prefix'),
//        ], function () {
//            $this->loadRoutesFrom(__DIR__.'/../routes/routes.php');
//        });

        Paginator::useBootstrapFive();
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super admin') ? true : null;
        });
    }
}
