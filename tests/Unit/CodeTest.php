<?php

declare(strict_types=1);

use Atlasflow\Eppo\Exceptions\InvalidArgumentException;
use Atlasflow\Eppo\Support\Code;

it('accepts and upper-cases valid EPPO codes', function (): void {
    expect(Code::eppo('bemita'))->toBe('BEMITA')
        ->and(Code::eppo(' 1ANIMK '))->toBe('1ANIMK')
        ->and(Code::eppo('GOSHI'))->toBe('GOSHI');
});

it('rejects codes that cannot exist', function (string $code): void {
    Code::eppo($code);
})->with(['ABC', 'TOOLONGCODE', 'BEM-TA', ''])->throws(InvalidArgumentException::class);

it('validates country and RPPO codes', function (): void {
    expect(Code::country('fr'))->toBe('FR')
        ->and(Code::rppo('9a'))->toBe('9A');

    expect(fn () => Code::country('FRA'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Code::rppo('A9'))->toThrow(InvalidArgumentException::class);
});
