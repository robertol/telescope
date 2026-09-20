<?php

namespace Laravel\Telescope;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sentinel\Http\Middleware\SentinelMiddleware;
use Laravel\Telescope\Actions\UninstallAction;
use Laravel\Telescope\Contracts\ClearableRepository;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\Contracts\PrunableRepository;
use Laravel\Telescope\Observability\Console\PruneCommand as ObservabilityPruneCommand;
use Laravel\Telescope\Observability\Contracts\ObservabilityTransport;
use Laravel\Telescope\Observability\ExceptionFingerprint;
use Laravel\Telescope\Observability\ObservabilityCollector;
use Laravel\Telescope\Observability\ObservabilityNormalizer;
use Laravel\Telescope\Observability\ObservabilitySanitizer;
use Laravel\Telescope\Observability\ObservabilityTraceContext;
use Laravel\Telescope\Observability\QueryFingerprint;
use Laravel\Telescope\Observability\Storage\ObservabilityEntriesRepository;
use Laravel\Telescope\Observability\Storage\ObservabilityEntryQuery;
use Laravel\Telescope\Observability\Storage\ObservabilityPruner;
use Laravel\Telescope\Observability\Transport\PostgresTransport;
use Laravel\Telescope\Storage\DashboardAggregator;
use Laravel\Telescope\Storage\DatabaseEntriesRepository;

class TelescopeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerCommands();
        $this->registerPublishing();
        $this->registerPrePackageUninstallListener();

        if (! config('telescope.enabled')) {
            return;
        }

        Route::middlewareGroup('telescope', [
            SentinelMiddleware::class.':telescope',
            ...config('telescope.middleware', ['web']),
        ]);

        $this->registerRoutes();
        $this->registerResources();

        Telescope::start($this->app);
        Telescope::listenForStorageOpportunities($this->app);
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        Route::group([
            'domain' => config('telescope.domain', null),
            'namespace' => 'Laravel\Telescope\Http\Controllers',
            'prefix' => config('telescope.path'),
            'middleware' => 'telescope',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    /**
     * Register the Telescope resources.
     *
     * @return void
     */
    protected function registerResources()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'telescope');
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $publishesMigrationsMethod = method_exists($this, 'publishesMigrations')
                ? 'publishesMigrations'
                : 'publishes';

            $this->{$publishesMigrationsMethod}([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'telescope-migrations');

            $this->publishes([
                __DIR__.'/../config/telescope.php' => config_path('telescope.php'),
            ], 'telescope-config');

            $this->publishes([
                __DIR__.'/../stubs/TelescopeServiceProvider.stub' => app_path('Providers/TelescopeServiceProvider.php'),
            ], 'telescope-provider');
        }
    }

    /**
     * Register the package's commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ClearCommand::class,
                Console\InstallCommand::class,
                Console\ListCommand::class,
                Console\PauseCommand::class,
                Console\PruneCommand::class,
                Console\PublishCommand::class,
                Console\ResumeCommand::class,
                Console\ShowCommand::class,
                ObservabilityPruneCommand::class,
            ]);
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/telescope.php', 'telescope'
        );

        $this->registerStorageDriver();
    }

    /**
     * Register the package storage driver.
     *
     * @return void
     */
    protected function registerStorageDriver()
    {
        $driver = config('telescope.driver');

        if (method_exists($this, $method = 'register'.ucfirst($driver).'Driver')) {
            $this->$method();
        }
    }

    /**
     * Register the package database storage driver.
     *
     * @return void
     */
    protected function registerDatabaseDriver()
    {
        $this->app->singleton(DatabaseEntriesRepository::class, function ($app) {
            return new DatabaseEntriesRepository(
                $app['config']->get('telescope.storage.database.connection'),
                $app['config']->get('telescope.storage.database.chunk')
            );
        });

        $this->app->singleton(ObservabilityTraceContext::class);
        $this->app->singleton(QueryFingerprint::class);
        $this->app->singleton(ExceptionFingerprint::class);
        $this->app->singleton(ObservabilitySanitizer::class);
        $this->app->singleton(ObservabilityNormalizer::class);

        $this->app->singleton(ObservabilityTransport::class, function ($app) {
            return new PostgresTransport(
                $app['config']->get('telescope.storage.database.connection'),
                (int) $app['config']->get(
                    'telescope.observability.chunk',
                    $app['config']->get('telescope.storage.database.chunk', 1000)
                )
            );
        });

        $this->app->singleton(ObservabilityCollector::class);
        $this->app->singleton(ObservabilityEntryQuery::class, function ($app) {
            return new ObservabilityEntryQuery(
                $app['config']->get('telescope.storage.database.connection')
            );
        });
        $this->app->singleton(ObservabilityPruner::class, function ($app) {
            return new ObservabilityPruner(
                $app['config']->get('telescope.storage.database.connection')
            );
        });

        $this->app->singleton(EntriesRepository::class, function ($app) {
            return new ObservabilityEntriesRepository(
                $app->make(DatabaseEntriesRepository::class),
                $app->make(ObservabilityCollector::class),
            );
        });

        $this->app->singleton(
            ClearableRepository::class,
            fn ($app) => $app->make(EntriesRepository::class)
        );

        $this->app->singleton(
            PrunableRepository::class,
            fn ($app) => $app->make(EntriesRepository::class)
        );

        $this->app->when(DatabaseEntriesRepository::class)
            ->needs('$connection')
            ->give(fn () => config('telescope.storage.database.connection'));

        $this->app->when(DatabaseEntriesRepository::class)
            ->needs('$chunkSize')
            ->give(fn () => config('telescope.storage.database.chunk'));

        $this->app->singleton(DashboardAggregator::class);

        $this->app->when(DashboardAggregator::class)
            ->needs('$connection')
            ->give(fn () => config('telescope.storage.database.connection'));
    }

    /**
     * Register a pre-package uninstallation listener.
     */
    protected function registerPrePackageUninstallListener(): void
    {
        $this->app['events']->listen('composer_package.laravel/telescope:pre_uninstall', function () {
            $this->app->make(UninstallAction::class)->handle();
        });
    }
}
