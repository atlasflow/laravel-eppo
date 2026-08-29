<?php

declare(strict_types=1);

use Atlasflow\Eppo\Cache\Models\EppoCacheEntry;
use Atlasflow\Eppo\Exceptions\AuthenticationException;
use Atlasflow\Eppo\Exceptions\BadRequestException;
use Atlasflow\Eppo\Exceptions\EppoException;
use Atlasflow\Eppo\Exceptions\MissingRecord;
use Atlasflow\Eppo\Resources\ToolsResource;
use Atlasflow\Eppo\Sync\ChangeSync;

/**
 * Contract tests against the real EPPO Global Database.
 *
 * These guard the shapes this package decodes. EPPO's OpenAPI document is not
 * always exact — the tags field, the rendition names and the 400-for-a-missing
 * code below were all discovered here rather than in the spec — so anything
 * surprising upstream should fail loudly in this file first.
 */
uses()->group('live');

it('reports the API as healthy', function (): void {
    $status = liveEppo()->status();

    expect($status->status)->toBe('ok')
        ->and($status->version)->not->toBe('')
        ->and($status->timestamp)->toBeGreaterThan(1_700_000_000);
});

it('reads a real taxon', function (): void {
    $taxon = liveEppo()->taxon('BEMITA')->overview();

    expect($taxon->eppocode)->toBe('BEMITA')
        ->and($taxon->prefname)->toBe('Bemisia tabaci')
        ->and($taxon->isActive)->toBeTrue()
        ->and($taxon->isUsable())->toBeTrue()
        ->and($taxon->datatype)->toBe('GAI')
        ->and($taxon->createdAt?->format('Y-m-d'))->toBe('2002-10-28');
});

it('reads names in several languages', function (): void {
    $names = liveEppo()->taxon('BEMITA')->names();

    expect($names->count())->toBeGreaterThan(10)
        ->and($names->firstWhere('preferred', true)->fullname)->toBe('Bemisia tabaci')
        ->and($names->firstWhere('preferred', true)->author)->toContain('Gennadius')
        ->and($names->pluck('langIso')->unique()->count())->toBeGreaterThan(3);
});

it('walks the taxonomy from kingdom to species', function (): void {
    $chain = liveEppo()->taxon('BEMITA')->taxonomy();

    expect($chain->first()->type)->toBe('Kingdom')
        ->and($chain->first()->prefname)->toBe('Animalia')
        ->and($chain->last()->type)->toBe('Species')
        ->and($chain->last()->eppocode)->toBe('BEMITA')
        ->and($chain->pluck('level')->all())->toBe(range(1, $chain->count()));
});

it('unwraps the nested kingdom response', function (): void {
    expect(liveEppo()->taxon('BEMITA')->kingdom()->prefname)->toBe('Animalia');
});

it('counts the records available for a taxon', function (): void {
    $infos = liveEppo()->taxon('BEMITA')->infos();

    expect($infos->hosts)->toBeGreaterThan(0)
        ->and($infos->distribution)->toBeGreaterThan(0)
        ->and($infos->categorization)->toBeGreaterThan(0);
});

it('reads hosts with classification and bibliography', function (): void {
    $hosts = liveEppo()->taxon('BEMITA')->hosts();

    expect($hosts->count())->toBeGreaterThan(10)
        ->and($hosts->pluck('classLabel')->unique())->toContain('Major host')
        ->and($hosts->firstWhere('eppocode', 'GOSHI')->prefname)->toBe('Gossypium hirsutum');
});

it('reads regulatory categorization per country', function (): void {
    $listings = liveEppo()->taxon('BEMITA')->categorization();

    expect($listings->count())->toBeGreaterThan(5)
        ->and($listings->pluck('qlistLabel')->unique())->toContain('A2 list')
        ->and($listings->first()->yearAdded)->toBeGreaterThan(1950);
});

it('reads distribution with status codes that resolve against the reference table', function (): void {
    $eppo = liveEppo();

    $distribution = $eppo->taxon('BEMITA')->distribution();
    $known = $eppo->references()->distributionStatuses()->pluck('pestStatus');

    expect($distribution->count())->toBeGreaterThan(50)
        ->and($distribution->pluck('pestStatus')->unique()->diff($known))->toBeEmpty();
});

it('reads vectors in both directions', function (): void {
    $eppo = liveEppo();

    expect($eppo->taxon('BEMITA')->vectorOf()->count())->toBeGreaterThan(10)
        ->and($eppo->taxon('BYVMV0')->vectors()->pluck('eppocode'))->toContain('BEMITA')
        ->and($eppo->taxon('BYVMV0')->vectors()->first()->vectorClassLabel)->toBe('Known vector');
});

it('reads biological control agents', function (): void {
    expect(liveEppo()->taxon('BEMITA')->biologicalControlAgents()->count())->toBeGreaterThan(0);
});

it('reads photos with array tags and dimension-named renditions', function (): void {
    $photo = liveEppo()->taxon('BEMITA')->photos()->first();

    expect($photo->tags)->toBeArray()->not->toBeEmpty()
        ->and($photo->files->count())->toBeGreaterThan(1)
        ->and($photo->largest()->width())->toBeGreaterThan($photo->thumbnail()->width())
        ->and($photo->url())->toStartWith('https://');
});

it('reads EPPO standards with their PDFs', function (): void {
    $standards = liveEppo()->taxon('BEMITA')->standards();

    expect($standards->count())->toBeGreaterThan(0)
        ->and($standards->first()->numstandard)->not->toBe('')
        ->and($standards->first()->files->first()->url)->toStartWith('https://');
});

it('reads reporting service articles for a taxon', function (): void {
    $articles = liveEppo()->taxon('BEMITA')->reportingArticles();

    expect($articles->count())->toBeGreaterThan(0)
        ->and($articles->first()->title)->not->toBe('')
        ->and($articles->first()->year)->toBeGreaterThan(2000);
});

it('assembles a full datasheet for a well-documented quarantine pest', function (): void {
    $sheet = liveEppo()->taxon('XYLEFA')->datasheet();

    expect($sheet->name())->toBe('Xylella fastidiosa')
        ->and($sheet->kingdom())->toBe('Bacteria')
        ->and($sheet->rank())->toBe('Species')
        ->and($sheet->sections())->toContain(
            'taxonomy', 'names', 'categorization', 'distribution', 'hosts',
            'vectors', 'standards', 'photos', 'reporting',
        )
        ->and($sheet->hosts->count())->toBeGreaterThan(500)
        ->and($sheet->majorHosts()->pluck('prefname'))->toContain('Olea europaea')
        ->and($sheet->currentListings()->keys())->toContain('A1 list', 'A2 list')
        ->and($sheet->countries()->count())->toBeGreaterThan(30)
        ->and($sheet->vectors->count())->toBeGreaterThan(20)
        ->and($sheet->namesByLanguage()->keys())->toContain('en', 'fr', 'la');
})->group('slow');

it('skips the sections EPPO reports as empty', function (): void {
    // A plant, so no pests-of-it, no vectors, no biological control agents.
    $sheet = liveEppo()->taxon('PHNFR')->datasheet();

    expect($sheet->name())->toBe('Photinia x fraseri')
        ->and($sheet->fetched)->not->toContain('vectors', 'vectorof', 'bca', 'bcaof')
        ->and($sheet->vectors)->toBeEmpty()
        ->and($sheet->taxonomy->count())->toBeGreaterThan(5);
});

it('reads a country with its subdivisions', function (): void {
    $france = liveEppo()->country('FR')->overview();

    expect($france->countryName)->toBe('France')
        ->and($france->continentName)->toBe('Europe')
        ->and($france->states->pluck('stateName'))->toContain('Corse');
});

it('reads what a country regulates and what is present there', function (): void {
    $eppo = liveEppo();

    expect($eppo->country('FR')->categorization()->count())->toBeGreaterThan(0)
        ->and($eppo->country('FR')->presence()->count())->toBeGreaterThan(100);
});

it('reads an RPPO and its member countries', function (): void {
    $eppo = liveEppo()->rppo('9A')->overview();

    expect($eppo->acronym)->toBe('EPPO')
        ->and($eppo->members->pluck('isocode'))->toContain('FR', 'DE', 'GB');
});

it('reads every reference table', function (): void {
    $references = liveEppo()->references();

    expect($references->countries()->count())->toBeGreaterThan(100)
        ->and($references->countriesStates()->get('AU')->pluck('stateName'))->toContain('Tasmania')
        ->and($references->qLists()->pluck('label'))->toContain('A1 list', 'A2 list')
        ->and($references->distributionStatuses()->count())->toBeGreaterThan(5)
        ->and($references->pestHostClassifications()->pluck('label'))->toContain('Major host')
        ->and($references->vectorClassifications()->pluck('label'))->toContain('Known vector')
        ->and($references->rppos()->pluck('acronym'))->toContain('EPPO', 'NAPPO');
});

it('reads the reporting service index and an issue', function (): void {
    $eppo = liveEppo();

    // Undocumented paging: without a limit the endpoint returns only 100.
    expect($eppo->reportings()->list()->count())->toBe(100);

    $issues = $eppo->reportings()->list(limit: 1000);

    expect($issues->count())->toBeGreaterThan(500);

    $detail = $eppo->reportings()->issue($issues->first()->reportingId);

    expect($detail->issue->reference)->toBe($issues->first()->reference)
        ->and($detail->articles->count())->toBeGreaterThan(0)
        ->and($detail->articles->pluck('articleId')->duplicates())->toBeEmpty();
});

it('resolves a name to a code and searches by keyword', function (): void {
    $tools = liveEppo()->tools();

    expect($tools->codeFor('Bemisia tabaci'))->toBe('BEMITA')
        ->and($tools->search('Bemisia', ToolsResource::MODE_STARTS_WITH)->pluck('eppocode'))
        ->toContain('BEMITA');
});

it('pages the taxon index and reports a plausible total', function (): void {
    $page = liveEppo()->taxons()->list(limit: 5, orderBy: 'eppocode');

    expect($page->items)->toHaveCount(5)
        ->and($page->total)->toBeGreaterThan(100_000)
        ->and($page->hasMore())->toBeTrue()
        ->and($page->nextOffset())->toBe(5);
});

it('reads the change feed the sync depends on', function (): void {
    $changed = collect(iterator_to_array(
        liveEppo()->taxons()->changedSince(now()->subDays(60)->format('Y-m-d'), pageSize: 50)
    ));

    expect($changed->count())->toBeGreaterThan(0)
        ->and($changed->first()->eppocode)->toMatch('/^[0-9A-Z]{5,6}$/');
})->group('slow');

it('answers an unknown but well-formed code with a missing record, not a 404', function (): void {
    $eppo = liveEppo();

    try {
        $eppo->taxon('QQQQQQ')->overview();
        $this->fail('EPPO accepted a code that should not exist.');
    } catch (EppoException $e) {
        expect($e)->toBeInstanceOf(MissingRecord::class)
            ->and($e)->toBeInstanceOf(BadRequestException::class)
            ->and($e->getCode())->toBe(400);
    }
});

it('rejects an invalid key', function (): void {
    liveEppo();

    $eppo = reconfigure(['eppo.key' => 'definitely-not-a-key']);

    expect(fn () => $eppo->status())->toThrow(AuthenticationException::class);
});

it('serves the second read from the durable cache, not the network', function (): void {
    $eppo = liveEppo();

    $eppo->taxon('BEMITA')->overview();

    $entry = EppoCacheEntry::query()->forSubject('BEMITA')->forResource('taxon.overview')->firstOrFail();

    expect($entry->status)->toBe(200)
        ->and($entry->expires_at)->toBeNull()
        ->and($entry->stale_at)->not->toBeNull();

    // Drop the hot tier and make the API unusable; the durable copy must answer.
    cache()->store('array')->clear();
    reconfigure(['eppo.key' => 'definitely-not-a-key']);

    expect(eppo()->taxon('BEMITA')->overview()->prefname)->toBe('Bemisia tabaci');
});

it('invalidates only what EPPO says changed', function (): void {
    $eppo = liveEppo();

    $eppo->taxon('BEMITA')->overview();
    $eppo->taxon('GOSHI')->overview();

    expect(EppoCacheEntry::query()->count())->toBe(2);

    // A window in which BEMITA (created 2002) cannot appear.
    $result = app(ChangeSync::class)->run(since: now()->subDay()->format('Y-m-d'));

    expect($result->codes)->not->toContain('BEMITA')
        ->and(EppoCacheEntry::query()->forSubject('BEMITA')->count())->toBe(1);
});
