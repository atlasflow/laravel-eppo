<?php

declare(strict_types=1);

use Atlasflow\Eppo\Cache\Models\EppoCacheEntry;
use Atlasflow\Eppo\Events\EntryInvalidated;
use Atlasflow\Eppo\Tests\Fixtures\Responses;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function warmTwoTaxa(): void
{
    Http::fake([
        '*overview' => Http::response(Responses::taxonOverview()),
        '*names' => Http::response(Responses::taxonNames()),
        '*hosts' => Http::response(Responses::taxonHosts()),
        '*' => Http::response([]),
    ]);

    foreach (['BEMITA', 'GOSHI'] as $code) {
        eppo()->taxon($code)->overview();
        eppo()->taxon($code)->names();
        eppo()->taxon($code)->hosts();
    }
}

it('busts every resource for one code and leaves other codes alone', function (): void {
    warmTwoTaxa();

    expect(EppoCacheEntry::query()->count())->toBe(6);

    $removed = cacheManager()->forgetSubject('BEMITA');

    expect($removed)->toBe(3)
        ->and(EppoCacheEntry::query()->forSubject('BEMITA')->count())->toBe(0)
        ->and(EppoCacheEntry::query()->forSubject('GOSHI')->count())->toBe(3);
});

it('busts a single resource across every code', function (): void {
    warmTwoTaxa();

    expect(cacheManager()->forgetResource('taxon.hosts'))->toBe(2)
        ->and(EppoCacheEntry::query()->forResource('taxon.hosts')->count())->toBe(0)
        ->and(EppoCacheEntry::query()->count())->toBe(4);
});

it('busts a resource group with a wildcard', function (): void {
    warmTwoTaxa();

    expect(cacheManager()->forgetResource('taxon.*'))->toBe(6)
        ->and(EppoCacheEntry::query()->count())->toBe(0);
});

it('clears the hot tier along with the durable row', function (): void {
    warmTwoTaxa();

    cacheManager()->forgetSubject('BEMITA');

    refake(['*' => Http::response(Responses::taxonOverview(name: 'Refetched'))]);

    expect(eppo()->taxon('BEMITA')->overview()->prefname)->toBe('Refetched');
});

it('announces what it invalidated', function (): void {
    warmTwoTaxa();
    Event::fake([EntryInvalidated::class]);

    cacheManager()->forgetSubject('BEMITA');

    Event::assertDispatched(
        EntryInvalidated::class,
        fn (EntryInvalidated $e) => $e->scope === 'subject' && $e->target === 'BEMITA' && $e->count === 3,
    );
});

it('misses everything after a version bump, without deleting anything', function (): void {
    warmTwoTaxa();

    cache()->store('array')->clear();
    refake(
        ['*' => Http::response(Responses::taxonOverview(name: 'Refetched'))],
        ['eppo.cache.version' => 'v2'],
    );

    expect(eppo()->taxon('BEMITA')->overview()->prefname)->toBe('Refetched')
        ->and(EppoCacheEntry::query()->where('version', 'v1')->count())->toBe(6);
});

it('prunes entries orphaned by a version bump', function (): void {
    warmTwoTaxa();

    reconfigure(['eppo.cache.version' => 'v2']);

    expect(cacheManager()->prune())->toBe(6)
        ->and(EppoCacheEntry::query()->count())->toBe(0);
});

it('prunes hard-expired entries when a keep_for window is set', function (): void {
    reconfigure(['eppo.cache.keep_for' => 60]);
    warmTwoTaxa();

    expect(EppoCacheEntry::query()->whereNull('expires_at')->count())->toBe(0);

    EppoCacheEntry::query()->update(['expires_at' => now()->subMinute()]);

    expect(cacheManager()->prune())->toBe(6);
});

it('flushes everything on demand', function (): void {
    warmTwoTaxa();

    expect(cacheManager()->flush())->toBe(6)
        ->and(EppoCacheEntry::query()->count())->toBe(0);
});

it('busts a code through the resource object', function (): void {
    warmTwoTaxa();

    expect(eppo()->taxon('BEMITA')->forget())->toBe(3);
});
