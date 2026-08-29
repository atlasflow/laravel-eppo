<?php

declare(strict_types=1);

use Atlasflow\Eppo\Data\Datasheet;
use Atlasflow\Eppo\Tests\Fixtures\Responses;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, int|null>  $infos
 */
function fakeTaxon(array $infos = []): void
{
    Http::fake([
        '*/overview' => Http::response(Responses::taxonOverview()),
        '*/infos' => Http::response(array_merge([
            'datasheet' => 1, 'categorization' => 0, 'distribution' => 0, 'pests' => 0,
            'hosts' => 0, 'pathwaypest' => 0, 'pathwayhost' => 0, 'photos' => 0,
            'expertise' => 0, 'reporting' => 0, 'documents' => 0, 'specimens' => 0,
            'vectorspests' => 0, 'vectorshosts' => 0, 'eppolinks' => 0, 'bca' => 0, 'bcao' => 0,
        ], $infos)),
        '*/taxonomy' => Http::response([
            ['eppocode' => '1ANIMK', 'prefname' => 'Animalia', 'level' => 1, 'type' => 'Kingdom'],
            ['eppocode' => 'BEMITA', 'prefname' => 'Bemisia tabaci', 'level' => 2, 'type' => 'Species'],
        ]),
        '*/names' => Http::response(Responses::taxonNames()),
        '*/hosts' => Http::response(Responses::taxonHosts()),
        '*/standards' => Http::response([]),
        '*' => Http::response([]),
    ]);
}

it('assembles one record from the endpoints that have data', function (): void {
    fakeTaxon(['hosts' => 61, 'categorization' => 21]);

    $sheet = eppo()->taxon('BEMITA')->datasheet();

    expect($sheet)->toBeInstanceOf(Datasheet::class)
        ->and($sheet->eppocode())->toBe('BEMITA')
        ->and($sheet->name())->toBe('Bemisia tabaci')
        ->and($sheet->kingdom())->toBe('Animalia')
        ->and($sheet->rank())->toBe('Species')
        ->and($sheet->infos->hosts)->toBe(61)
        ->and($sheet->hosts)->toHaveCount(2)
        ->and($sheet->names)->toHaveCount(2);
});

it('never requests a section EPPO says is empty', function (): void {
    fakeTaxon(['hosts' => 61]);

    $sheet = eppo()->taxon('BEMITA')->datasheet();

    // Counted and present.
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/hosts'));

    // Counted as zero: never asked for.
    foreach (['photos', 'distribution', 'categorization', 'pests', 'vectors', 'vectorof', 'bca', 'bcaof', 'documents', 'reporting_articles'] as $skipped) {
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/'.$skipped));
    }

    expect($sheet->fetched)->toBe(['taxonomy', 'names', 'hosts', 'standards'])
        ->and($sheet->photos)->toBeEmpty()
        ->and($sheet->distribution)->toBeEmpty();
});

it('costs five calls for a taxon EPPO holds little about', function (): void {
    fakeTaxon();

    eppo()->taxon('BEMITA')->datasheet();

    // overview, infos, taxonomy, names, standards — and nothing else.
    Http::assertSentCount(5);
});

it('honours an explicit section list', function (): void {
    fakeTaxon(['hosts' => 61, 'photos' => 37, 'distribution' => 307]);

    $sheet = eppo()->taxon('BEMITA')->datasheet(['names', 'hosts']);

    expect($sheet->fetched)->toBe(['names', 'hosts'])
        ->and($sheet->taxonomy)->toBeEmpty()
        ->and($sheet->photos)->toBeEmpty();

    Http::assertSentCount(4); // overview, infos, names, hosts
    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/photos'));
});

it('reports which sections carry records', function (): void {
    fakeTaxon(['hosts' => 61]);

    $sheet = eppo()->taxon('BEMITA')->datasheet();

    expect($sheet->sections())->toBe(['taxonomy', 'names', 'hosts'])
        ->and($sheet->has('hosts'))->toBeTrue()
        ->and($sheet->has('photos'))->toBeFalse()
        ->and($sheet->has('nonsense'))->toBeFalse()
        ->and($sheet->counts()['hosts'])->toBe(2)
        ->and($sheet->counts()['photos'])->toBe(0);
});

it('groups hosts by classification, commonest first', function (): void {
    fakeTaxon(['hosts' => 2]);

    $sheet = eppo()->taxon('BEMITA')->datasheet(['hosts']);

    expect($sheet->hostsByClass()->keys()->all())->toBe(['Major host', 'Minor host'])
        ->and($sheet->majorHosts()->pluck('eppocode')->all())->toBe(['GOSHI']);
});

it('separates current listings from withdrawn ones', function (): void {
    Http::fake([
        '*/overview' => Http::response(Responses::taxonOverview()),
        '*/infos' => Http::response(['categorization' => 3]),
        '*/categorization' => Http::response([
            ['country_iso' => 'FR', 'qlist' => '2', 'qlist_label' => 'A2 list', 'year_add' => 1981, 'year_delete' => null],
            ['country_iso' => 'DE', 'qlist' => '2', 'qlist_label' => 'A2 list', 'year_add' => 1981, 'year_delete' => null],
            ['country_iso' => 'GB', 'qlist' => '1', 'qlist_label' => 'A1 list', 'year_add' => 1979, 'year_delete' => 2020],
        ]),
        '*' => Http::response([]),
    ]);

    $sheet = eppo()->taxon('BEMITA')->datasheet(['categorization']);

    expect($sheet->categorization)->toHaveCount(3)
        ->and($sheet->currentListings()->keys()->all())->toBe(['A2 list'])
        ->and($sheet->currentListings()->get('A2 list'))->toHaveCount(2);
});

it('reads through the cache like everything else', function (): void {
    fakeTaxon(['hosts' => 61]);

    eppo()->taxon('BEMITA')->datasheet();
    eppo()->taxon('BEMITA')->datasheet();

    // overview, infos, taxonomy, names, hosts, standards — then nothing.
    Http::assertSentCount(6);
});
