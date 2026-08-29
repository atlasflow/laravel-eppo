<?php

declare(strict_types=1);

use Atlasflow\Eppo\Resources\ToolsResource;
use Illuminate\Support\Facades\Http;

it('reads a country with its states', function (): void {
    Http::fake(['*' => Http::response([
        'country_iso' => 'FR',
        'country_name' => 'France',
        'continent_id' => 1,
        'continent_name' => 'Europe',
        'states' => [['state_id' => 'FR-COR', 'state_name' => 'Corse']],
    ])]);

    $country = eppo()->country('fr')->overview();

    expect($country->countryIso)->toBe('FR')
        ->and($country->states)->toHaveCount(1)
        ->and($country->states->first()->stateName)->toBe('Corse');
});

it('reads what a country regulates', function (): void {
    Http::fake(['*' => Http::response([[
        'eppocode' => 'BEMITA', 'prefname' => 'Bemisia tabaci', 'qlist' => '2',
        'qlist_label' => 'A2 list', 'year_add' => 1981, 'year_del' => null, 'year_transient' => null,
    ]])]);

    $listed = eppo()->country('FR')->categorization();

    expect($listed->first()->qlistLabel)->toBe('A2 list')
        ->and($listed->first()->yearAdded)->toBe(1981)
        ->and($listed->first()->yearDeleted)->toBeNull();
});

it('reads an RPPO and its members', function (): void {
    Http::fake(['*' => Http::response([
        'rppo_code' => '9A', 'rppo_acronym' => 'EPPO', 'rppo_name' => 'European and Mediterranean PPO',
        'members' => [['isocode' => 'FR', 'country' => 'France'], ['isocode' => 'GB', 'country' => 'United Kingdom']],
    ])]);

    expect(eppo()->rppo('9a')->overview()->members)->toHaveCount(2);
});

it('reads distribution records', function (): void {
    Http::fake(['*' => Http::response([[
        'country_iso' => 'ES', 'state_id' => null, 'peststatus' => 'Present, widespread',
        'yr_situation' => '2020', 'yr_introd' => null, 'yr_erad' => null,
    ]])]);

    $distribution = eppo()->taxon('BEMITA')->distribution();

    expect($distribution->first()->countryIso)->toBe('ES')
        ->and($distribution->first()->pestStatus)->toBe('Present, widespread');
});

it('reads photos and picks a rendition', function (): void {
    Http::fake(['*' => Http::response([[
        'photo_id' => 12, 'lastmod' => '2020-01-01', 'descinfo' => 'Adults', 'authors' => 'EPPO', 'tags' => 'adult',
        'files' => [
            ['size' => 'thumb', 'url' => 'https://gd.eppo.int/thumb.jpg'],
            ['size' => 'large', 'url' => 'https://gd.eppo.int/large.jpg'],
        ],
    ]])]);

    $photo = eppo()->taxon('BEMITA')->photos()->first();

    expect($photo->url('large'))->toBe('https://gd.eppo.int/large.jpg')
        ->and($photo->url('missing-size'))->toBe('https://gd.eppo.int/thumb.jpg');
});

it('reads standards and picks a language', function (): void {
    Http::fake(['*' => Http::response([[
        'standard_id' => 3, 'numstandard' => 'PM 7/35', 'title' => 'Bemisia tabaci',
        'files' => [['filename' => 'pm7-35-en.pdf', 'lang' => 'en', 'url' => 'https://pp1.eppo.int/en.pdf']],
    ]])]);

    expect(eppo()->taxon('BEMITA')->standards()->first()->urlFor('en'))
        ->toBe('https://pp1.eppo.int/en.pdf');
});

it('reads reference tables', function (): void {
    Http::fake([
        '*references/qList' => Http::response([['qlist' => '1', 'qlist_label' => 'A1 list']]),
        '*references/countriesStates' => Http::response([
            'FR' => [['state_code' => 'FR-COR', 'state_name' => 'Corse']],
        ]),
        '*references/pestHostClassification' => Http::response([['class_id' => 1, 'class_label' => 'Major host']]),
    ]);

    expect(eppo()->references()->qLists()->first()->label)->toBe('A1 list')
        ->and(eppo()->references()->countriesStates()->get('FR')->first()->stateName)->toBe('Corse')
        ->and(eppo()->references()->pestHostClassifications()->first()->label)->toBe('Major host');
});

it('reads the reporting service', function (): void {
    Http::fake([
        '*reportings/list' => Http::response([[
            'reporting_id' => 1, 'numrs' => '01', 'datecreate' => '2011-01-01',
            'repyear' => 2011, 'reference' => 'Rse-2011-01',
        ]]),
        '*reportings/article/*' => Http::response([
            'article_id' => 99, 'numarticle' => '2011/001', 'title' => 'First record',
            'datecreate' => '2011-01-01', 'lastmodif' => '2011-02-01',
            'sources' => 'NPPO', 'content' => 'Body', 'eppocodes' => ['BEMITA', 'GOSHI'],
        ]),
    ]);

    expect(eppo()->reportings()->list()->first()->reference)->toBe('Rse-2011-01')
        ->and(eppo()->reportings()->article(99)->eppocodes)->toBe(['BEMITA', 'GOSHI']);
});

it('reads the taxonomy chain and the record counts', function (): void {
    Http::fake([
        '*taxonomy' => Http::response([
            ['eppocode' => '1ANIMK', 'prefname' => 'Animalia', 'level' => 1, 'type' => 'Kingdom'],
            ['eppocode' => '1INSEC', 'prefname' => 'Insecta', 'level' => 2, 'type' => 'Class'],
        ]),
        '*infos' => Http::response(['datasheet' => 1, 'hosts' => 400, 'photos' => 12]),
        '*kingdom' => Http::response(['eppocode' => ['eppocode' => '1ANIMK', 'prefname' => 'Animalia']]),
    ]);

    expect(eppo()->taxon('BEMITA')->taxonomy()->last()->type)->toBe('Class')
        ->and(eppo()->taxon('BEMITA')->infos()->hosts)->toBe(400)
        ->and(eppo()->taxon('BEMITA')->kingdom()->prefname)->toBe('Animalia');
});

it('passes the search mode through', function (): void {
    Http::fake(['*' => Http::response([])]);

    eppo()->tools()->search('Bemisia', ToolsResource::MODE_CONTAINS);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'searchMode=3'));
});

it('reaches an endpoint the package does not wrap', function (): void {
    Http::fake(['*' => Http::response(['status' => 'ok'])]);

    expect(eppo()->raw('/status', 'status'))->toBe(['status' => 'ok']);
});
