<?php

namespace App\Providers;

use App\Events\UserLockChanged;
use App\Events\UserStatusChanged;
use App\Listeners\WriteUserStatusLog;
use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\Field\Group as FieldGroup;
use App\Models\Media;
use App\Models\Media\Library as MediaLibrary;
use App\Models\EntryTree;
use App\Models\Status;
use App\Models\User;
use App\Observers\EntryTreeObserver;
use App\Observers\FieldValueObserver;
use App\Observers\StatusObserver;
use App\Rest\Api;
use App\Services\CategoryService;
use App\Services\EntryAuthorService;
use App\Services\FieldService;
use App\Services\FilesService;
use App\Services\Media\NullTransformationDriver;
use App\Services\Media\TransformationDriverInterface;
use App\Services\MediaStorageService;
use App\Services\UserService;
use App\Settings;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind by class name so constructor injection works in controllers,
        // then alias to 'settings' for backwards-compatible app('settings') calls.
        $this->app->singleton(Settings::class, fn() => new Settings());
        $this->app->alias(Settings::class, 'settings');

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
        $this->app->singleton(EntryAuthorService::class, fn() => new EntryAuthorService());

        // Media layer
        $this->app->bind(TransformationDriverInterface::class, NullTransformationDriver::class);
        $this->app->singleton('media-service', fn() => new MediaStorageService());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Decouple stored polymorphic type strings from class names so that
        // model renames do not silently orphan rows in polymorphic tables.
        Relation::morphMap([
            'entry' => Entry::class,
            'entry_group' => EntryGroup::class,
            'entry_type' => EntryType::class,
            'category' => Category::class,
            'category_group' => CategoryGroup::class,
            'field_group' => FieldGroup::class,
            'media' => Media::class,
            'media_library' => MediaLibrary::class,
            'user' => User::class,
        ]);

        // Model observers
        Status::observe(StatusObserver::class);
        EntryTree::observe(EntryTreeObserver::class);
        \App\Models\FieldValue::observe(FieldValueObserver::class);

        // User status audit log listeners
        Event::listen(UserStatusChanged::class, WriteUserStatusLog::class);
        Event::listen(UserLockChanged::class, WriteUserStatusLog::class);

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
        Paginator::useTailwind();
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super admin') ? true : null;
        });

        Status::observe(StatusObserver::class);
    }
}
