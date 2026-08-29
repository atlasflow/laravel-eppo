<?php

declare(strict_types=1);

use Atlasflow\Eppo\Cache\CacheKey;
use Atlasflow\Eppo\Http\Endpoint;

it('normalises query order so equivalent requests share one cache entry', function (): void {
    $a = Endpoint::make('/tools/search', 'tools.search', null, ['keyword' => 'Bemisia', 'searchMode' => 2]);
    $b = Endpoint::make('/tools/search', 'tools.search', null, ['searchMode' => 2, 'keyword' => 'Bemisia']);

    expect($a->signature())->toBe($b->signature())
        ->and(CacheKey::for($a, 'v1'))->toBe(CacheKey::for($b, 'v1'));
});

it('drops null query parameters', function (): void {
    $endpoint = Endpoint::make('/taxons/list', 'taxons.list', null, ['limit' => 100, 'updatedFromDate' => null]);

    expect($endpoint->query)->toBe(['limit' => 100]);
});

it('renders booleans the way the API expects', function (): void {
    $endpoint = Endpoint::make('/tools/name2codes', 'tools.name2codes', null, ['name' => 'x', 'onlyPreferred' => true]);

    expect($endpoint->url('https://api.eppo.int/gd/v2'))
        ->toBe('https://api.eppo.int/gd/v2/tools/name2codes?name=x&onlyPreferred=true');
});

it('survives a round trip through job serialisation', function (): void {
    $endpoint = Endpoint::make('/taxons/taxon/BEMITA/hosts', 'taxon.hosts', 'BEMITA', ['a' => 1]);

    expect(Endpoint::fromArray($endpoint->jsonSerialize()))->toEqual($endpoint);
});

it('changes every key when the cache version is bumped', function (): void {
    $endpoint = Endpoint::make('/status', 'status');

    expect(CacheKey::for($endpoint, 'v1'))->not->toBe(CacheKey::for($endpoint, 'v2'));
});
