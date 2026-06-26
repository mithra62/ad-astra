<?php

namespace App\Providers;

use App\Events\UserLockChanged;
use App\Events\UserStatusChanged;
use App\Listeners\WriteUserStatusLog;
use App\EntryTypes\BlogPostEntryType;
use App\EntryTypes\EventEntryType;
use App\EntryTypes\GeneralEntryType;
use App\EntryTypes\JobListingEntryType;
use App\EntryTypes\NewsArticleEntryType;
use App\EntryTypes\PageEntryType;
use App\EntryTypes\PodcastEpisodeEntryType;
use App\EntryTypes\PortfolioItemEntryType;
use App\EntryTypes\ProductEntryType;
use App\EntryTypes\RecipeEntryType;
use App\EntryTypes\VideoEntryType;
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
use App\Services\Media\GDTransformationDriver;
use App\Services\Media\NullTransformationDriver;
use App\Services\Media\TransformationDriverInterface;
use App\Services\MediaStorageService;
use App\Services\UserService;
use App\Settings;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
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
        $this->app->singleton(Settings::class, fn () => new Settings());
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

        $this->app->singleton(UserService::class, fn () => new UserService());
        $this->app->singleton(CategoryService::class, fn ($app) => new CategoryService($app));
        $this->app->singleton(EntryAuthorService::class, fn () => new EntryAuthorService());

        // Media layer
        $this->app->bind(TransformationDriverInterface::class, function () {
            if (extension_loaded('imagick')) {
                return new \App\Services\Media\ImagickTransformationDriver();
            }
            if (extension_loaded('gd')) {
                return new GDTransformationDriver();
            }
            return new NullTransformationDriver();
        });
        $this->app->singleton('media-service', fn () => new MediaStorageService());

        // Schema macro for the status-denormalization column triple. FK to
        // `statuses` is intentionally NOT included — this codebase uses a
        // deferred-FK pattern (e.g. 2026_05_07_000003_add_media_foreign_keys.php)
        // because `statuses` doesn't exist until April 2026. Add the FK in a
        // follow-up migration once the table exists.
        Blueprint::macro('statusColumns', function (): void {
            /** @var Blueprint $this */
            $this->unsignedBigInteger('status_id')->nullable()->index();
            $this->string('status_handle')->nullable()->index();
            $this->boolean('status_is_public')->default(false)->index();
        });
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

            // Entry behavior concrete classes, keyed by behavior handle
            'behavior.general' => GeneralEntryType::class,
            'behavior.blog-post' => BlogPostEntryType::class,
            'behavior.product' => ProductEntryType::class,
            'behavior.page' => PageEntryType::class,
            'behavior.event' => EventEntryType::class,
            'behavior.job-listing' => JobListingEntryType::class,
            'behavior.news-article' => NewsArticleEntryType::class,
            'behavior.podcast-episode' => PodcastEpisodeEntryType::class,
            'behavior.portfolio-item' => PortfolioItemEntryType::class,
            'behavior.recipe' => RecipeEntryType::class,
            'behavior.video' => VideoEntryType::class,
        ]);

        // Status sync: force-boot every HasStatus consumer so the trait's
        // bootHasStatus() fires and registers each one with StatusSyncRegistry
        // BEFORE StatusObserver might read the registry. Without this, a queue
        // worker or artisan command that only touches Status (and no consumer)
        // would find an empty registry and silently no-op the cascade.
        // Add new consumers here when adopting HasStatus on a new model.
        foreach ([Entry::class, Media::class] as $statusConsumer) {
            new $statusConsumer();
        }

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

        // Expose the resolved appearance preference (light|dark|system) to every
        // top-level view so the admin/auth layouts can apply the `.dark` class
        // before first paint. Bound to '*' (not the layout names) because Twig
        // resolves `{% extends %}` internally, so a composer on the layout view
        // would never fire — only the controller's top-level view does, and the
        // layout inherits its context. Settings::get() applies the authenticated
        // user's override automatically and is cached, so this stays cheap.
        View::composer('*', function ($view) {
            $view->with('appearance', app(Settings::class)->get('general', 'appearance', 'light'));
        });

        Paginator::useTailwind();
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super admin') ? true : null;
        });
    }
}
