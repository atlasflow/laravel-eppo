<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Tests;

use Atlasflow\Eppo\EppoServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [EppoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');

        $app['config']->set('eppo.key', 'test-key');
        $app['config']->set('eppo.base_url', 'https://api.eppo.int/gd/v2');
        $app['config']->set('eppo.throttle.enabled', false);
        $app['config']->set('eppo.retry.times', 1);
        $app['config']->set('eppo.retry.base_delay_ms', 1);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
    }
}
