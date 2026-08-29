<?php

declare(strict_types=1);

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Cache\NullStore;
use Atlasflow\Eppo\Cache\Ttl;
use Atlasflow\Eppo\Contracts\Transport;
use Atlasflow\Eppo\Http\Endpoint;

function manager(array $ttl): CacheManager
{
    $transport = new class implements Transport
    {
        public function get(Endpoint $endpoint): array
        {
            return [];
        }
    };

    return new CacheManager($transport, new NullStore, null, ['ttl' => $ttl]);
}

it('prefers an exact resource match', function (): void {
    $cache = manager(['default' => 10, 'taxon.*' => 20, 'taxon.hosts' => 30]);

    expect($cache->ttlFor('taxon.hosts'))->toBe(30);
});

it('falls back to the group wildcard, then the default', function (): void {
    $cache = manager(['default' => 10, 'taxon.*' => 20]);

    expect($cache->ttlFor('taxon.photos'))->toBe(20)
        ->and($cache->ttlFor('country.presence'))->toBe(10);
});

it('treats a null TTL as never going stale', function (): void {
    $cache = manager(['default' => Ttl::days(90), 'references.*' => Ttl::FOREVER]);

    expect($cache->ttlFor('references.countries'))->toBeNull();
});
