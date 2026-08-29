<?php

declare(strict_types=1);

use Atlasflow\Eppo\Cache\Models\EppoCacheEntry;
use Atlasflow\Eppo\Tests\Fixtures\Responses;
use Illuminate\Support\Facades\Http;

function warmOne(string $code = 'BEMITA'): void
{
    Http::fake([
        '*taxons/list*' => Http::response(Responses::taxonsList([])),
        '*names' => Http::response(Responses::taxonNames()),
        '*status' => Http::response(['status' => 'ok', 'timestamp' => 1762946810, 'version' => '2.0']),
        '*' => Http::response(Responses::taxonOverview($code)),
    ]);

    eppo()->taxon($code)->overview();
    eppo()->taxon($code)->names();
}

it('reports API health and cache size', function (): void {
    warmOne();

    $this->artisan('eppo:status')
        ->expectsOutputToContain('EPPO API 2.0')
        ->assertSuccessful();
});

it('warms the resources it is given', function (): void {
    Http::fake(['*' => Http::response(Responses::taxonOverview())]);

    $this->artisan('eppo:cache:warm', ['codes' => ['BEMITA'], '--with' => 'overview,names'])
        ->assertSuccessful();

    expect(EppoCacheEntry::query()->forSubject('BEMITA')->count())->toBe(2);
});

it('tops up every code already in the cache when given none', function (): void {
    warmOne();
    cacheManager()->forgetResource('taxon.names');

    $this->artisan('eppo:cache:warm', ['--with' => 'names'])->assertSuccessful();

    expect(EppoCacheEntry::query()->forResource('taxon.names')->count())->toBe(1);
});

it('rejects codes that are not EPPO codes', function (): void {
    Http::fake();

    $this->artisan('eppo:cache:warm', ['codes' => ['not-a-code']])->assertSuccessful();

    Http::assertNothingSent();
});

it('busts one subject from the command line', function (): void {
    warmOne();

    $this->artisan('eppo:cache:forget', ['target' => 'bemita'])
        ->expectsOutputToContain('Removed 2 entries')
        ->assertSuccessful();

    expect(EppoCacheEntry::query()->count())->toBe(0);
});

it('busts a resource group from the command line', function (): void {
    warmOne();

    $this->artisan('eppo:cache:forget', ['--resource' => 'taxon.*'])->assertSuccessful();

    expect(EppoCacheEntry::query()->count())->toBe(0);
});

it('needs a target', function (): void {
    $this->artisan('eppo:cache:forget')->assertExitCode(2);
});

it('flushes with --all', function (): void {
    warmOne();

    $this->artisan('eppo:cache:forget', ['--all' => true])->assertSuccessful();

    expect(EppoCacheEntry::query()->count())->toBe(0);
});

it('prunes orphaned versions', function (): void {
    warmOne();
    reconfigure(['eppo.cache.version' => 'v9']);

    $this->artisan('eppo:cache:prune')
        ->expectsOutputToContain('Pruned 2 entries')
        ->assertSuccessful();
});

it('refreshes only stale entries', function (): void {
    warmOne();

    EppoCacheEntry::query()->where('resource', 'taxon.names')->update(['stale_at' => now()->subDay()]);

    $this->artisan('eppo:cache:refresh')
        ->expectsOutputToContain('Refreshed 1 entries')
        ->assertSuccessful();
});

it('reports the changed codes in a dry run without touching the cache', function (): void {
    Http::fake([
        '*taxons/list*' => Http::response(Responses::taxonsList(['BEMITA'])),
        '*' => Http::response(Responses::taxonOverview()),
    ]);

    eppo()->taxon('BEMITA')->overview();

    $this->artisan('eppo:sync', ['--since' => '2026-08-01', '--dry-run' => true])
        ->expectsOutputToContain('BEMITA')
        ->assertSuccessful();

    expect(EppoCacheEntry::query()->forSubject('BEMITA')->count())->toBe(1);
});

it('invalidates changed codes on a real sync run', function (): void {
    Http::fake([
        '*taxons/list*' => Http::response(Responses::taxonsList(['BEMITA'])),
        '*' => Http::response(Responses::taxonOverview()),
    ]);

    eppo()->taxon('BEMITA')->overview();

    $this->artisan('eppo:sync', ['--since' => '2026-08-01'])
        ->expectsOutputToContain('Sync complete')
        ->assertSuccessful();

    expect(EppoCacheEntry::query()->forSubject('BEMITA')->count())->toBe(0);
});
