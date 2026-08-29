<?php

declare(strict_types=1);

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Contracts\DurableStore;
use Atlasflow\Eppo\Contracts\Transport;
use Atlasflow\Eppo\Eppo;
use Atlasflow\Eppo\Tests\TestCase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

uses(TestCase::class)->in('Feature');

function eppo(): Eppo
{
    return app(Eppo::class);
}

function cacheManager(): CacheManager
{
    return app(CacheManager::class);
}

/**
 * Apply config and rebuild the container bindings that read it. The package
 * binds its services as singletons, so a config change after the first resolve
 * would otherwise be invisible.
 *
 * @param  array<string, mixed>  $config
 */
function reconfigure(array $config = []): Eppo
{
    foreach ($config as $key => $value) {
        config()->set($key, $value);
    }

    foreach ([Transport::class, DurableStore::class, CacheManager::class, Eppo::class] as $abstract) {
        app()->forgetInstance($abstract);
    }

    return app(Eppo::class);
}

/**
 * Replace the HTTP stubs outright.
 *
 * `Http::fake()` merges new stubs behind the existing ones and the first match
 * wins, so a second call cannot override the first. This rebuilds the client
 * factory and the package services that hold it.
 *
 * @param  array<string, mixed>|Closure  $stubs
 * @param  array<string, mixed>  $config
 */
function refake(array|Closure $stubs, array $config = []): Eppo
{
    Http::clearResolvedInstances();
    app()->forgetInstance(HttpFactory::class);
    Http::fake($stubs);

    return reconfigure($config);
}
