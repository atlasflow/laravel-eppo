<?php

declare(strict_types=1);

use Atlasflow\Eppo\Cache\Models\EppoCacheEntry;
use Atlasflow\Eppo\Exceptions\MissingRecord;
use Atlasflow\Eppo\Tests\Fixtures\Responses;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

it('ships with caching off', function (): void {
    // The suite turns it on; this is what the published config actually says.
    $shipped = require __DIR__.'/../../config/eppo.php';

    expect($shipped['cache']['enabled'])->toBeFalse();
});

it('goes to the API every time when caching is off', function (): void {
    refake(['*' => Http::response(Responses::taxonOverview())], ['eppo.cache.enabled' => false]);

    eppo()->taxon('BEMITA')->overview();
    eppo()->taxon('BEMITA')->overview();
    eppo()->taxon('BEMITA')->overview();

    Http::assertSentCount(3);
    expect(EppoCacheEntry::query()->count())->toBe(0);
});

it('writes nothing to the database when caching is off', function (): void {
    refake(['*' => Http::response(['code' => 400, 'error' => 'Bad request'], 400)], ['eppo.cache.enabled' => false]);

    try {
        eppo()->taxon('QQQQQQ')->overview();
    } catch (MissingRecord) {
        // expected
    }

    expect(EppoCacheEntry::query()->count())->toBe(0);
});

it('still works with the durable tier off but L1 on', function (): void {
    refake(
        ['*' => Http::response(Responses::taxonOverview())],
        ['eppo.cache.enabled' => true, 'eppo.cache.durable.enabled' => false],
    );

    eppo()->taxon('BEMITA')->overview();
    eppo()->taxon('BEMITA')->overview();

    Http::assertSentCount(1);                            // served from L1
    expect(EppoCacheEntry::query()->count())->toBe(0);   // nothing persisted
});

it('reads the table name from configuration', function (): void {
    expect((new EppoCacheEntry)->getTable())->toBe('eppo_cache_entries');

    config()->set('eppo.cache.durable.table', 'my_eppo_cache');

    expect((new EppoCacheEntry)->getTable())->toBe('my_eppo_cache');
});

it('creates its tables on the configured connection', function (): void {
    expect(Schema::hasTable('eppo_cache_entries'))->toBeTrue()
        ->and(Schema::hasTable('eppo_sync_state'))->toBeTrue()
        ->and(Schema::hasColumns('eppo_cache_entries', [
            'key', 'version', 'resource', 'subject', 'path', 'query',
            'status', 'payload', 'compressed', 'payload_hash',
            'fetched_at', 'stale_at', 'expires_at', 'hits', 'last_hit_at',
        ]))->toBeTrue();
});

it('tells you the cache is off instead of failing on a missing table', function (): void {
    reconfigure(['eppo.cache.enabled' => false]);

    foreach (['eppo:cache:warm', 'eppo:cache:refresh', 'eppo:cache:prune', 'eppo:sync'] as $command) {
        $this->artisan($command)
            ->expectsOutputToContain('EPPO caching is off')
            ->assertFailed();
    }

    $this->artisan('eppo:cache:forget', ['target' => 'BEMITA'])
        ->expectsOutputToContain('EPPO caching is off')
        ->assertFailed();
});

it('reports the cache as disabled in eppo:status', function (): void {
    refake(['*' => Http::response(['status' => 'ok', 'timestamp' => 1762946810, 'version' => '2.0'])], [
        'eppo.cache.enabled' => false,
    ]);

    $this->artisan('eppo:status')
        ->expectsOutputToContain('disabled')
        ->assertSuccessful();
});
