<?php

declare(strict_types=1);

use Atlasflow\Eppo\Support\Cast;

it('reads numeric strings as integers', function (): void {
    expect(Cast::int(['year_add' => '1981'], 'year_add'))->toBe(1981)
        ->and(Cast::nullableInt(['year_delete' => null], 'year_delete'))->toBeNull()
        ->and(Cast::nullableInt(['year_delete' => ''], 'year_delete'))->toBeNull();
});

it('treats empty strings as null', function (): void {
    expect(Cast::nullableString(['bibref' => ''], 'bibref'))->toBeNull()
        ->and(Cast::nullableString(['bibref' => 'EPPO'], 'bibref'))->toBe('EPPO');
});

it('parses both date formats EPPO returns, and ignores zero dates', function (): void {
    expect(Cast::date(['d' => '2002-10-28 00:00:00'], 'd')?->format('Y-m-d'))->toBe('2002-10-28')
        ->and(Cast::date(['d' => '2011-01-15'], 'd')?->format('Y-m-d'))->toBe('2011-01-15')
        ->and(Cast::date(['d' => '0000-00-00 00:00:00'], 'd'))->toBeNull()
        ->and(Cast::date(['d' => 'not a date'], 'd'))->toBeNull();
});

it('coerces the booleans the API sends as 0 and 1', function (): void {
    expect(Cast::bool(['preferred' => 1], 'preferred'))->toBeTrue()
        ->and(Cast::bool(['preferred' => '0'], 'preferred'))->toBeFalse()
        ->and(Cast::bool(['preferred' => true], 'preferred'))->toBeTrue()
        ->and(Cast::bool([], 'preferred', true))->toBeTrue();
});
