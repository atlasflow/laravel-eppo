<?php

declare(strict_types=1);

use Atlasflow\Eppo\Cache\Models\EppoCacheEntry;
use Atlasflow\Eppo\Events\CacheHit;
use Atlasflow\Eppo\Events\StaleEntryServed;
use Atlasflow\Eppo\Exceptions\NotFoundException;
use Atlasflow\Eppo\Exceptions\ServerException;
use Atlasflow\Eppo\Jobs\RefreshCacheEntry;
use Atlasflow\Eppo\Tests\Fixtures\Responses;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('stores a response in the durable table and answers the next call from it', function (): void {
    Http::fake(['*' => Http::response(Responses::taxonOverview())]);

    eppo()->taxon('BEMITA')->overview();
    eppo()->taxon('BEMITA')->overview();

    Http::assertSentCount(1);

    $entry = EppoCacheEntry::query()->firstOrFail();

    expect($entry->resource)->toBe('taxon.overview')
        ->and($entry->subject)->toBe('BEMITA')
        ->and($entry->path)->toBe('/taxons/taxon/BEMITA/overview')
        ->and($entry->status)->toBe(200)
        ->and($entry->expires_at)->toBeNull()          // durable: never hard-expires
        ->and($entry->stale_at)->not->toBeNull();      // but does get revalidated
});

it('survives the L1 tier being wiped', function (): void {
    Http::fake(['*' => Http::response(Responses::taxonOverview())]);

    eppo()->taxon('BEMITA')->overview();

    cache()->store('array')->clear();

    expect(eppo()->taxon('BEMITA')->overview()->prefname)->toBe('Bemisia tabaci');

    Http::assertSentCount(1);
});

it('reports which tier answered', function (): void {
    Http::fake(['*' => Http::response(Responses::taxonOverview())]);
    Event::fake([CacheHit::class]);

    eppo()->taxon('BEMITA')->overview();
    eppo()->taxon('BEMITA')->overview();

    Event::assertDispatched(CacheHit::class, fn (CacheHit $e) => $e->tier === 'l1');
});

it('serves a stale entry immediately and queues the revalidation', function (): void {
    Http::fake(['*' => Http::response(Responses::taxonOverview())]);

    eppo()->taxon('BEMITA')->overview();

    // Age the entry past its stale time and drop the hot tier.
    EppoCacheEntry::query()->update(['stale_at' => now()->subDay()]);
    cache()->store('array')->clear();

    Queue::fake();
    Event::fake([StaleEntryServed::class]);

    $taxon = eppo()->taxon('BEMITA')->overview();

    expect($taxon->prefname)->toBe('Bemisia tabaci');

    Http::assertSentCount(1); // the read itself did not go upstream
    Queue::assertPushed(RefreshCacheEntry::class);
    Event::assertDispatched(StaleEntryServed::class, fn (StaleEntryServed $e) => $e->revalidating === true);
});

it('queues only one revalidation however many readers hit the stale entry', function (): void {
    Http::fake(['*' => Http::response(Responses::taxonOverview())]);

    eppo()->taxon('BEMITA')->overview();
    EppoCacheEntry::query()->update(['stale_at' => now()->subDay()]);
    cache()->store('array')->clear();

    Queue::fake();

    eppo()->taxon('BEMITA')->overview();
    cache()->store('array')->forget('eppo:'.EppoCacheEntry::query()->value('key'));
    eppo()->taxon('BEMITA')->overview();

    Queue::assertPushed(RefreshCacheEntry::class, 1);
});

it('serves a stale entry when EPPO is unreachable', function (): void {
    // First call succeeds; every later call fails, as if EPPO went down.
    Http::fake(['*' => Http::sequence()
        ->push(Responses::taxonOverview())
        ->pushStatus(500)
        ->whenEmpty(Http::response(['error' => 'boom'], 500))]);

    reconfigure(['eppo.cache.revalidate.enabled' => false]);
    eppo()->taxon('BEMITA')->overview();

    EppoCacheEntry::query()->update(['stale_at' => now()->subDay()]);
    cache()->store('array')->clear();

    Event::fake([StaleEntryServed::class]);

    expect(eppo()->taxon('BEMITA')->overview()->prefname)->toBe('Bemisia tabaci');

    Event::assertDispatched(StaleEntryServed::class, fn (StaleEntryServed $e) => $e->becauseOfError === true);
});

it('rethrows when EPPO is unreachable and nothing is cached', function (): void {
    Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

    expect(fn () => eppo()->taxon('BEMITA')->overview())
        ->toThrow(ServerException::class);
});

it('caches an absence so a missing code is not re-requested', function (): void {
    Http::fake(['*' => Http::response(['error' => 'Not found'], 404)]);

    expect(fn () => eppo()->taxon('ZZZZZ')->overview())->toThrow(NotFoundException::class);

    cache()->store('array')->clear();

    expect(fn () => eppo()->taxon('ZZZZZ')->overview())->toThrow(NotFoundException::class);

    Http::assertSentCount(1);
    expect(EppoCacheEntry::query()->negative()->count())->toBe(1);
});

it('gives negative entries their own, shorter stale time', function (): void {
    Http::fake(['*' => Http::response(['error' => 'Not found'], 404)]);

    try {
        eppo()->taxon('ZZZZZ')->overview();
    } catch (NotFoundException) {
        // expected
    }

    $negative = EppoCacheEntry::query()->negative()->firstOrFail();

    expect($negative->stale_at->diffInDays($negative->fetched_at))
        ->toEqualWithDelta(-7, 1);
});

it('does not persist a miss when negative caching is off', function (): void {
    reconfigure(['eppo.cache.durable.cache_misses' => false]);
    Http::fake(['*' => Http::response(['error' => 'Not found'], 404)]);

    try {
        eppo()->taxon('ZZZZZ')->overview();
    } catch (NotFoundException) {
        // expected
    }

    expect(EppoCacheEntry::query()->count())->toBe(0);
});

it('bypasses the cache when asked, and writes the fresh copy back', function (): void {
    Http::fake(['*' => Http::sequence()
        ->push(Responses::taxonOverview(name: 'Old name'))
        ->push(Responses::taxonOverview(name: 'Bemisia tabaci'))]);

    expect(eppo()->taxon('BEMITA')->overview()->prefname)->toBe('Old name')
        ->and(eppo()->fresh()->taxon('BEMITA')->overview()->prefname)->toBe('Bemisia tabaci')
        ->and(eppo()->taxon('BEMITA')->overview()->prefname)->toBe('Bemisia tabaci');

    Http::assertSentCount(2);
});

it('keeps different query strings as different entries', function (): void {
    Http::fake(['*' => Http::response(Responses::search())]);

    eppo()->tools()->search('Bemisia');
    eppo()->tools()->search('Bemisia', onlyPreferred: true);

    expect(EppoCacheEntry::query()->where('resource', 'tools.search')->count())->toBe(2);
    Http::assertSentCount(2);
});

it('compresses payloads when configured to', function (): void {
    reconfigure(['eppo.cache.durable.compress' => true]);
    Http::fake(['*' => Http::response(Responses::taxonOverview())]);

    eppo()->taxon('BEMITA')->overview();
    cache()->store('array')->clear();

    expect(EppoCacheEntry::query()->value('compressed'))->toBeTruthy()
        ->and(eppo()->taxon('BEMITA')->overview()->prefname)->toBe('Bemisia tabaci');

    Http::assertSentCount(1);
});
