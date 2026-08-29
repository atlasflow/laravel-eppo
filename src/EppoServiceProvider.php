<?php

declare(strict_types=1);

namespace Atlasflow\Eppo;

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Cache\DatabaseStore;
use Atlasflow\Eppo\Cache\NullStore;
use Atlasflow\Eppo\Console\CacheForgetCommand;
use Atlasflow\Eppo\Console\CachePruneCommand;
use Atlasflow\Eppo\Console\CacheRefreshCommand;
use Atlasflow\Eppo\Console\CacheWarmCommand;
use Atlasflow\Eppo\Console\StatusCommand;
use Atlasflow\Eppo\Console\SyncCommand;
use Atlasflow\Eppo\Contracts\DurableStore;
use Atlasflow\Eppo\Contracts\Transport;
use Atlasflow\Eppo\Http\HttpTransport;
use Atlasflow\Eppo\Http\Throttle;
use Atlasflow\Eppo\Sync\ChangeSync;
use Atlasflow\Eppo\Sync\SyncState;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

final class EppoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/eppo.php', 'eppo');

        $this->app->singleton(Transport::class, function ($app): Transport {
            /** @var Config $config */
            $config = $app->make('config');

            return new HttpTransport(
                http: $app->make(HttpFactory::class),
                apiKey: $config->get('eppo.key'),
                baseUrl: (string) $config->get('eppo.base_url'),
                fallbackUrls: (array) $config->get('eppo.fallback_urls', []),
                timeout: (int) $config->get('eppo.timeout', 15),
                connectTimeout: (int) $config->get('eppo.connect_timeout', 5),
                userAgent: (string) $config->get('eppo.user_agent', 'atlasflow/laravel-eppo'),
                retry: (array) $config->get('eppo.retry'),
                throttle: $this->makeThrottle($app),
            );
        });

        $this->app->singleton(DurableStore::class, function ($app): DurableStore {
            /** @var Config $config */
            $config = $app->make('config');

            if (! $config->get('eppo.cache.durable.enabled', true)) {
                return new NullStore;
            }

            return new DatabaseStore(
                connection: $app->make(DatabaseManager::class)->connection($config->get('eppo.cache.durable.connection')),
                table: (string) $config->get('eppo.cache.durable.table', 'eppo_cache_entries'),
                compress: (bool) $config->get('eppo.cache.durable.compress', false),
            );
        });

        $this->app->singleton(CacheManager::class, function ($app): CacheManager {
            /** @var Config $config */
            $config = $app->make('config');

            /** @var array<string, mixed> $cacheConfig */
            $cacheConfig = (array) $config->get('eppo.cache', []);

            return new CacheManager(
                transport: $app->make(Transport::class),
                durable: $app->make(DurableStore::class),
                l1: $config->get('eppo.cache.l1.enabled', true)
                    ? $app->make(CacheFactory::class)->store($config->get('eppo.cache.l1.store'))
                    : null,
                config: $cacheConfig,
                bus: $app->bound(BusDispatcher::class) ? $app->make(BusDispatcher::class) : null,
            );
        });

        $this->app->singleton(Eppo::class, fn ($app): Eppo => new Eppo($app->make(CacheManager::class)));

        $this->app->singleton(SyncState::class, function ($app): SyncState {
            /** @var Config $config */
            $config = $app->make('config');

            return new SyncState(
                connection: $app->make(DatabaseManager::class)->connection($config->get('eppo.cache.durable.connection')),
                table: (string) $config->get('eppo.cache.durable.sync_table', 'eppo_sync_state'),
            );
        });

        $this->app->singleton(ChangeSync::class, function ($app): ChangeSync {
            /** @var Config $config */
            $config = $app->make('config');

            return new ChangeSync(
                eppo: $app->make(Eppo::class),
                cache: $app->make(CacheManager::class),
                state: $app->make(SyncState::class),
                config: (array) $config->get('eppo.sync', []),
                warmResources: (array) $config->get('eppo.warm.resources', []),
            );
        });

        $this->app->alias(Eppo::class, 'eppo');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/eppo.php' => $this->app->configPath('eppo.php'),
            ], 'eppo-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'eppo-migrations');

            $this->commands([
                StatusCommand::class,
                SyncCommand::class,
                CacheWarmCommand::class,
                CacheRefreshCommand::class,
                CacheForgetCommand::class,
                CachePruneCommand::class,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [Transport::class, DurableStore::class, CacheManager::class, Eppo::class, ChangeSync::class, SyncState::class, 'eppo'];
    }

    private function makeThrottle(Application $app): ?Throttle
    {
        /** @var Config $config */
        $config = $app->make('config');

        if (! $config->get('eppo.throttle.enabled', true)) {
            return null;
        }

        return new Throttle(
            cache: $app->make(CacheFactory::class)->store($config->get('eppo.throttle.store')),
            maxRequests: (int) $config->get('eppo.throttle.max_requests', 1800),
            windowSeconds: (int) $config->get('eppo.throttle.per_seconds', 10),
            maxWaitSeconds: (int) $config->get('eppo.throttle.max_wait_seconds', 12),
        );
    }
}
