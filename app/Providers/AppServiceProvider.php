<?php

namespace App\Providers;

use App\Rest\Api;
use App\Settings;
use App\Services\FieldService;
use App\Services\FilesService;
use App\Services\CategoryService;
use App\Services\UserService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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

        $this->app->singleton('fields-service', function ($app) {
            return new FieldService($app);
        });

        $this->app->singleton(UserService::class, fn() => new UserService());
        $this->app->singleton(CategoryService::class, fn($app) => new CategoryService($app));
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

        //setup template routing
        View::addNamespace('templates', resource_path('templates'));
        View::addNamespace('admin', resource_path('views/admin'));
        Paginator::useBootstrapFive();
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super admin') ? true : null;
        });
    }
}
