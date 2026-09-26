<?php

namespace EduLazaro\Laraterms\Tests;

use EduLazaro\Laraterms\LaratermsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Boots the package on an in-memory SQLite database.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Run the package migrations before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Get the package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LaratermsServiceProvider::class];
    }

    /**
     * Use an in-memory SQLite database and the package config.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('laraterms', require __DIR__ . '/../config/laraterms.php');
    }
}
