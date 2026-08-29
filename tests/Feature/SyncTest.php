<?php

declare(strict_types=1);

use Atlasflow\Eppo\Cache\Models\EppoCacheEntry;
use Atlasflow\Eppo\Events\TaxonChanged;
use Atlasflow\Eppo\Sync\ChangeSync;
use Atlasflow\Eppo\Sync\SyncState;
use Atlasflow\Eppo\Tests\Fixtures\Responses;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * Warm two codes, then have the change feed report that only one moved.
 */
function warmThenReportChanged(array $changed): void
{
    Http::fake([
        '*taxons/list*' => Http::response(Responses::taxonsList($changed)),
        '*' => Http::response(Responses::taxonOverview()),
    ]);

    foreach (['BEMITA', 'GOSHI'] as $code) {
        eppo()->taxon($code)->overview();
        eppo()->taxon($code)->names();
    }
}

it('invalidates only the codes EPPO reports as changed', function (): void {
    warmThenReportChanged(['BEMITA']);

    expect(EppoCacheEntry::query()->count())->toBe(4);

    $result = app(ChangeSync::class)->run(since: '2026-08-01');

    expect($result->scanned)->toBe(1)
        ->and($result->invalidatedEntries)->toBe(2)
        ->and(EppoCacheEntry::query()->forSubject('BEMITA')->count())->toBe(0)
        ->and(EppoCacheEntry::query()->forSubject('GOSHI')->count())->toBe(2);
});

it('does nothing when nothing changed', function (): void {
    warmThenReportChanged([]);

    $result = app(ChangeSync::class)->run(since: '2026-08-01');

    expect($result->scanned)->toBe(0)
        ->and($result->invalidatedEntries)->toBe(0)
        ->and(EppoCacheEntry::query()->count())->toBe(4);
});

it('reads the change feed from EPPO every run, never from the cache', function (): void {
    warmThenReportChanged(['BEMITA']);

    app(ChangeSync::class)->run(since: '2026-08-01');
    app(ChangeSync::class)->run(since: '2026-08-01');

    $listCalls = collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/taxons/list'))
        ->count();

    expect($listCalls)->toBe(2);
});

it('asks the change feed for the date range it was given', function (): void {
    warmThenReportChanged(['BEMITA']);

    app(ChangeSync::class)->run(since: '2026-08-01');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/taxons/list')
        && str_contains($request->url(), 'createdFromDate=2026-08-01')
        && str_contains($request->url(), 'updatedFromDate=2026-08-01'));
});

it('announces each changed taxon', function (): void {
    warmThenReportChanged(['BEMITA']);
    Event::fake([TaxonChanged::class]);

    app(ChangeSync::class)->run(since: '2026-08-01');

    Event::assertDispatched(TaxonChanged::class, fn (TaxonChanged $e) => $e->taxon->eppocode === 'BEMITA'
        && $e->invalidatedEntries === 2);
});

it('remembers where it finished so the next run starts there', function (): void {
    warmThenReportChanged(['BEMITA']);

    app(ChangeSync::class)->run(since: '2026-01-01');

    $state = app(SyncState::class);

    expect($state->lastRunAt())->not->toBeNull()
        ->and($state->lastChangeDate()?->format('Y-m-d'))->toBe(now()->format('Y-m-d'));
});

it('rewinds by the overlap window on the following run', function (): void {
    warmThenReportChanged(['BEMITA']);

    app(ChangeSync::class)->run(since: '2026-01-01');

    expect(app(ChangeSync::class)->resolveSince()->format('Y-m-d'))
        ->toBe(now()->subDays(2)->format('Y-m-d'));
});

it('falls back to the configured initial window on a first run', function (): void {
    config()->set('eppo.sync.initial_since', '-30 days');
    app()->forgetInstance(ChangeSync::class);

    expect(app(ChangeSync::class)->resolveSince()->format('Y-m-d'))
        ->toBe(now()->subDays(30)->format('Y-m-d'));
});

it('re-fetches invalidated resources when asked to', function (): void {
    warmThenReportChanged(['BEMITA']);

    $result = app(ChangeSync::class)->run(since: '2026-08-01', refresh: true);

    expect($result->refreshed)->toBe(2)
        ->and(EppoCacheEntry::query()->forSubject('BEMITA')->count())->toBe(2);
});

it('pages through a long change feed', function (): void {
    Http::fake([
        '*taxons/list*offset=0*' => Http::response(Responses::taxonsList(['AAAAA', 'BBBBB'], 0, 4)),
        '*taxons/list*' => Http::response(Responses::taxonsList(['CCCCC', 'DDDDD'], 2, 4)),
    ]);

    $result = app(ChangeSync::class)->run(since: '2026-08-01');

    expect($result->scanned)->toBe(4)
        ->and($result->codes)->toBe(['AAAAA', 'BBBBB', 'CCCCC', 'DDDDD']);
});
