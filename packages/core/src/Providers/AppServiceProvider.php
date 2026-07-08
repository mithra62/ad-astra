<?php

namespace AdAstra\Providers;

use AdAstra\Console\Commands\DoctorCommand;
use AdAstra\Console\Commands\RefreshTokens;
use AdAstra\Console\Commands\ValidateClassReferences;
use AdAstra\Doctor\Checks\Database\ConnectionCheck;
use AdAstra\Doctor\Checks\Database\PendingMigrationsCheck;
use AdAstra\Doctor\Checks\Database\RequiredTablesCheck;
use AdAstra\Doctor\Checks\EntrySystem\BehaviorClassReferencesCheck;
use AdAstra\Doctor\Checks\Environment\AppDebugCheck;
use AdAstra\Doctor\Checks\Environment\AppKeyCheck;
use AdAstra\Doctor\Checks\Environment\LaravelVersionCheck;
use AdAstra\Doctor\Checks\Environment\PhpVersionCheck;
use AdAstra\Doctor\Checks\FieldSystem\FieldTypeClassReferencesCheck;
use AdAstra\Doctor\Checks\Media\TransformationDriverCheck;
use AdAstra\Doctor\Checks\Permissions\RequiredPermissionsCheck;
use AdAstra\Doctor\Checks\Permissions\RequiredRolesCheck;
use AdAstra\Doctor\Checks\Storage\PublicSymlinkCheck;
use AdAstra\Doctor\Checks\Storage\StorageWritableCheck;
use AdAstra\Doctor\DoctorRunner;
use AdAstra\EntryTypes\BlogPostEntryType;
use AdAstra\EntryTypes\EventEntryType;
use AdAstra\EntryTypes\GeneralEntryType;
use AdAstra\EntryTypes\JobListingEntryType;
use AdAstra\EntryTypes\NewsArticleEntryType;
use AdAstra\EntryTypes\PageEntryType;
use AdAstra\EntryTypes\PodcastEpisodeEntryType;
use AdAstra\EntryTypes\PortfolioItemEntryType;
use AdAstra\EntryTypes\ProductEntryType;
use AdAstra\EntryTypes\RecipeEntryType;
use AdAstra\EntryTypes\VideoEntryType;
use AdAstra\Events\UserLockChanged;
use AdAstra\Events\UserStatusChanged;
use AdAstra\Listeners\WriteUserStatusLog;
use AdAstra\Models\Category;
use AdAstra\Models\Category\Group as CategoryGroup;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryTree;
use AdAstra\Models\EntryType;
use AdAstra\Models\Field\Group as FieldGroup;
use AdAstra\Models\FieldValue;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Library as MediaLibrary;
use AdAstra\Models\Status;
use AdAstra\Models\User;
use AdAstra\Observers\EntryTreeObserver;
use AdAstra\Observers\FieldValueObserver;
use AdAstra\Observers\StatusObserver;
use AdAstra\Rest\Api;
use AdAstra\Services\CategoryService;
use AdAstra\Services\EntryAuthorService;
use AdAstra\Services\FieldService;
use AdAstra\Services\FilesService;
use AdAstra\Services\GateBypassRecorder;
use AdAstra\Services\Media\GDTransformationDriver;
use AdAstra\Services\Media\ImagickTransformationDriver;
use AdAstra\Services\Media\NullTransformationDriver;
use AdAstra\Services\Media\TransformationDriverInterface;
use AdAstra\Services\MediaStorageService;
use AdAstra\Services\UserService;
use AdAstra\Settings;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // AdAstra's own config ships in the package (merged so config('settings') /
        // config('site') resolve), and is publishable so a developer can override it at
        // the app's config/ root. Laravel + third-party config stays at the app root.
        $this->mergeConfigFrom(__DIR__ . '/../../config/settings.php', 'settings');
        $this->mergeConfigFrom(__DIR__ . '/../../config/site.php', 'site');
        $this->mergeConfigFrom(__DIR__ . '/../../config/doctor.php', 'doctor');
        $this->publishes([
            __DIR__ . '/../../config/settings.php' => config_path('settings.php'),
            __DIR__ . '/../../config/site.php' => config_path('site.php'),
            __DIR__ . '/../../config/doctor.php' => config_path('doctor.php'),
        ], 'adastra-config');

        // Doctor checks. The tag is the extension point: any package can add
        // its own checks by tagging them 'adastra.doctor.checks' in its
        // service provider — see docs/DOCTOR_EXTENDING.md. Tag order sets
        // report order among independent checks.
        $this->app->tag([
            PhpVersionCheck::class,
            LaravelVersionCheck::class,
            AppKeyCheck::class,
            AppDebugCheck::class,
            ConnectionCheck::class,
            RequiredTablesCheck::class,
            PendingMigrationsCheck::class,
            StorageWritableCheck::class,
            PublicSymlinkCheck::class,
            TransformationDriverCheck::class,
            RequiredRolesCheck::class,
            RequiredPermissionsCheck::class,
            BehaviorClassReferencesCheck::class,
            FieldTypeClassReferencesCheck::class,
        ], 'adastra.doctor.checks');
        $this->app->bind(DoctorRunner::class, fn ($app) => new DoctorRunner($app->tagged('adastra.doctor.checks')));

        // Framework models live under AdAstra\Models\, but their factories remain in
        // the Database\Factories\ namespace. Laravel's default guessers assume the
        // application namespace (App\), so neither direction bridges to AdAstra\ on its
        // own. Map both. (Stage 2 will move factories into the package and use per-model
        // newFactory() instead.)
        //
        // model -> factory, e.g. AdAstra\Models\Entry -> Database\Factories\EntryFactory
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'
                . Str::after($modelName, 'AdAstra\\Models\\') . 'Factory'
        );
        // factory -> model (for factories that omit $model), e.g.
        // Database\Factories\StatusGroupFactory -> AdAstra\Models\StatusGroup
        Factory::guessModelNamesUsing(function (Factory $factory) {
            $basename = Str::replaceLast(
                'Factory',
                '',
                Str::replaceFirst('Database\\Factories\\', '', get_class($factory))
            );
            $modelsClass = 'AdAstra\\Models\\' . $basename;

            return class_exists($modelsClass) ? $modelsClass : 'AdAstra\\' . $basename;
        });

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
        $this->app->singleton(GateBypassRecorder::class, fn () => new GateBypassRecorder());
        $this->app->singleton(CategoryService::class, fn ($app) => new CategoryService($app));
        $this->app->singleton(EntryAuthorService::class, fn () => new EntryAuthorService());

        // Media layer
        $this->app->bind(TransformationDriverInterface::class, function () {
            if (extension_loaded('imagick')) {
                return new ImagickTransformationDriver();
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
        FieldValue::observe(FieldValueObserver::class);

        // User status audit log listeners
        Event::listen(UserStatusChanged::class, WriteUserStatusLog::class);
        Event::listen(UserLockChanged::class, WriteUserStatusLog::class);

        // Package HTTP routes. Registered in a booted() callback so they load AFTER
        // every other provider (notably Fortify) has registered its routes — the site
        // catch-all in web.php (`/{uri?}`) must remain the last web route or it would
        // shadow /login and friends.
        // The catch-all in web.php (`/{uri?}` with `.*`) matches EVERY path, including
        // /admin/* and /api/*, so it must be registered LAST. Load admin + api first.
        $routes = __DIR__ . '/../../routes';
        $this->app->booted(function () use ($routes) {
            Route::middleware('web')->group($routes . '/admin.php');
            Route::middleware('api')->prefix('api')->group($routes . '/api.php');
            Route::middleware('web')->group($routes . '/web.php');
        });

        // Framework Artisan commands (schedule + the `inspire` demo command live in the
        // package's routes/console.php, loaded via bootstrap/app.php `commands:`).
        if ($this->app->runningInConsole()) {
            $this->commands([
                DoctorCommand::class,
                RefreshTokens::class,
                ValidateClassReferences::class,
            ]);
        }

        // Framework migrations ship inside the package (the version-delivery mechanism);
        // `php artisan migrate` picks them up here alongside any app migrations.
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // View resolution. Framework views ship in the package; the app's own
        // resource_path() locations are listed FIRST so a developer can override any
        // template by dropping a same-named file at the root (upgrade-safe override).
        $res = __DIR__ . '/../../resources';
        View::addNamespace('templates', [resource_path('templates'), $res . '/templates']);
        View::addNamespace('admin', [resource_path('views/admin'), $res . '/views/admin']);
        // Non-namespaced framework views (_fields.*, _inc.*, auth.*, errors.*). The app's
        // resource_path('views') is already the primary path (config/view.php), so this
        // package location is the fallback.
        View::addLocation($res . '/views');

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

        // Super admin gate bypass, with an audit trail. Spatie caches the
        // roles relation on the model instance after first load, so repeated
        // hasRole() calls within a request do not re-query.
        Gate::before(function ($user, $ability, array $arguments = []) {
            if (!$user->hasRole('super admin')) {
                return null; // zero added work for non-super-admins
            }

            app(GateBypassRecorder::class)->record($user, $ability, $arguments);

            return true;
        });

        // Flush the gate-bypass audit buffer after the response is sent
        // (terminating covers HTTP + artisan) and after every queue job —
        // long-running workers never call terminate() per job, and flushing
        // per job attributes rows to the job that produced them.
        $this->app->terminating(fn () => app(GateBypassRecorder::class)->flush());
        Queue::after(fn ($event) => app(GateBypassRecorder::class)->flush([
            'job' => $event->job->resolveName(),
        ]));
        Queue::failing(fn ($event) => app(GateBypassRecorder::class)->flush([
            'job' => $event->job->resolveName(),
        ]));
    }
}
