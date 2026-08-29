<?php

declare(strict_types=1);

use Atlasflow\Eppo\EppoServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

/**
 * Configuration set before the provider registers must not wipe the rest of
 * the block. Laravel's own mergeConfigFrom is a shallow array_merge, which
 * would silently collapse every TTL to the fallback and leave the durable
 * table nameless — with no error anywhere.
 *
 * @param  array<string, mixed>  $config
 */
function mergedConfig(array $config): Repository
{
    $container = new Container;
    $repository = new Repository;

    foreach ($config as $key => $value) {
        $repository->set($key, $value);
    }

    $container->instance('config', $repository);

    (new EppoServiceProvider($container))->register();

    return $repository;
}

it('keeps the rest of a block when one nested key is set first', function (): void {
    $config = mergedConfig(['eppo.cache.enabled' => true]);

    // The TTL keys contain dots themselves, so read the map, not a dot path.
    $ttl = (array) $config->get('eppo.cache.ttl');

    expect($config->get('eppo.cache.enabled'))->toBeTrue()
        ->and($ttl)->toHaveCount(22)
        ->and($ttl['taxon.overview'])->toBe(180 * 86400)
        ->and($ttl['references.*'])->toBe(365 * 86400)
        ->and($config->get('eppo.cache.l1.ttl'))->toBe(3600)
        ->and($config->get('eppo.cache.durable.table'))->toBe('eppo_cache_entries')
        ->and($config->get('eppo.cache.version'))->toBe('v1');
});

it('lets the application win on the keys it actually set', function (): void {
    $config = mergedConfig([
        'eppo.cache.durable.table' => 'my_cache',
        'eppo.retry.times' => 1,
        'eppo.fallback_urls' => ['https://api2025.eppo.dev:6443/gd/v2'],
    ]);

    expect($config->get('eppo.cache.durable.table'))->toBe('my_cache')
        ->and($config->get('eppo.cache.durable.sync_table'))->toBe('eppo_sync_state')
        ->and($config->get('eppo.retry.times'))->toBe(1)
        ->and($config->get('eppo.retry.max_delay_ms'))->toBe(10000)
        // Lists are replaced wholesale, not merged element by element.
        ->and($config->get('eppo.fallback_urls'))->toBe(['https://api2025.eppo.dev:6443/gd/v2']);
});

it('leaves the packaged defaults alone when nothing was set first', function (): void {
    $config = mergedConfig([]);

    expect($config->get('eppo.cache.enabled'))->toBeFalse()
        ->and($config->get('eppo.base_url'))->toBe('https://api.eppo.int/gd/v2')
        ->and($config->get('eppo.throttle.max_requests'))->toBe(1800);
});
