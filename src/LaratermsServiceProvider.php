<?php

namespace EduLazaro\Laraterms;

use EduLazaro\Laraterms\Support\HandleGenerator;
use EduLazaro\Laraterms\Taxonomy\TaxonomyRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the taxonomy registry, the manager and the handle generator.
 */
class LaratermsServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laraterms.php', 'laraterms');

        $this->app->singleton(TaxonomyRegistry::class, function ($app) {
            return new TaxonomyRegistry(config('laraterms.taxonomies', []));
        });

        $this->app->singleton('laraterms', function ($app) {
            return new LaratermsManager($app->make(TaxonomyRegistry::class));
        });

        // Bind your own HandleGenerator to change how handles are built.
        $this->app->bind(HandleGenerator::class, function () {
            return new HandleGenerator(
                locale: config('laraterms.handle.locale', 'en'),
                separator: config('laraterms.handle.separator', '-'),
                uniqueWithinScope: (bool) config('laraterms.handle.unique_within_scope', true),
            );
        });
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/laraterms.php' => config_path('laraterms.php'),
            ], 'laraterms-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'laraterms-migrations');
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
