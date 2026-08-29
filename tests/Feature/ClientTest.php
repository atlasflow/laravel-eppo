<?php

declare(strict_types=1);

use Atlasflow\Eppo\Exceptions\AuthenticationException;
use Atlasflow\Eppo\Exceptions\BadRequestException;
use Atlasflow\Eppo\Exceptions\ConfigurationException;
use Atlasflow\Eppo\Exceptions\InvalidArgumentException;
use Atlasflow\Eppo\Exceptions\MissingRecord;
use Atlasflow\Eppo\Exceptions\NotFoundException;
use Atlasflow\Eppo\Exceptions\RateLimitException;
use Atlasflow\Eppo\Tests\Fixtures\Responses;
use Illuminate\Support\Facades\Http;

it('sends the API key as an X-Api-Key header', function (): void {
    Http::fake(['*' => Http::response(Responses::taxonOverview())]);

    eppo()->taxon('BEMITA')->overview();

    Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'test-key')
        && $request->url() === 'https://api.eppo.int/gd/v2/taxons/taxon/BEMITA/overview');
});

it('maps an overview onto a typed taxon', function (): void {
    Http::fake(['*' => Http::response(Responses::taxonOverview())]);

    $taxon = eppo()->taxon('bemita')->overview();

    expect($taxon->eppocode)->toBe('BEMITA')
        ->and($taxon->prefname)->toBe('Bemisia tabaci')
        ->and($taxon->isActive)->toBeTrue()
        ->and($taxon->isUsable())->toBeTrue()
        ->and($taxon->createdAt?->format('Y-m-d'))->toBe('2002-10-28');
});

it('maps list endpoints onto collections of DTOs', function (): void {
    Http::fake(['*names' => Http::response(Responses::taxonNames())]);

    $names = eppo()->taxon('BEMITA')->names();

    expect($names)->toHaveCount(2)
        ->and($names->first()->fullname)->toBe('Bemisia tabaci')
        ->and($names->firstWhere('preferred', true)->author)->toBe('(Gennadius)');
});

it('reads hosts with their classification', function (): void {
    Http::fake(['*hosts' => Http::response(Responses::taxonHosts())]);

    $hosts = eppo()->taxon('BEMITA')->hosts();

    expect($hosts)->toHaveCount(2)
        ->and($hosts->first()->classLabel)->toBe('Major host')
        ->and($hosts->last()->bibref)->toBeNull();
});

it('resolves a name to a code', function (): void {
    Http::fake(['*name2codes*' => Http::response([['eppocode' => 'BEMITA', 'preferred' => true]])]);

    expect(eppo()->tools()->codeFor('Bemisia tabaci'))->toBe('BEMITA');
});

it('searches by keyword', function (): void {
    Http::fake(['*search*' => Http::response(Responses::search())]);

    $results = eppo()->tools()->search('Bemisia');

    expect($results->first()->eppocode)->toBe('BEMITA')
        ->and($results->first()->isPreferred)->toBeTrue();
});

it('rejects a search keyword shorter than the API allows without calling out', function (): void {
    Http::fake();

    expect(fn () => eppo()->tools()->search('be'))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('turns a 404 into a NotFoundException', function (): void {
    Http::fake(['*' => Http::response(['error' => 'Article not found'], 404)]);

    expect(fn () => eppo()->reportings()->article(999999))->toThrow(NotFoundException::class);
});

it('treats the 400 EPPO returns for an unknown code as a missing record', function (): void {
    // Verified live: /taxons/taxon/QQQQQQ/overview answers 400 {"code":400,
    // "error":"Bad request"}, not 404. Shape is validated before we send, so a
    // 400 can only mean the record is absent.
    Http::fake(['*' => Http::response(['code' => 400, 'error' => 'Bad request'], 400)]);

    expect(fn () => eppo()->taxon('QQQQQQ')->overview())->toThrow(BadRequestException::class);
    expect(eppo()->taxon('QQQQQQ')->exists())->toBeFalse();
    expect(new BadRequestException('x', 400, '/'))->toBeInstanceOf(MissingRecord::class);
});

it('turns a 401 into an AuthenticationException and does not retry it', function (): void {
    Http::fake(['*' => Http::response(['error' => 'API key is invalid or inactive'], 401)]);

    expect(fn () => eppo()->taxon('BEMITA')->overview())
        ->toThrow(AuthenticationException::class, 'API key is invalid or inactive');

    Http::assertSentCount(1);
});

it('retries a 429 and reports the rate limit when attempts run out', function (): void {
    $eppo = reconfigure(['eppo.retry.times' => 2]);

    Http::fake(['*' => Http::response([
        'code' => 429,
        'error' => 'Too many requests',
        'message' => 'Rate limit exceeded. Please try again later.',
        'rate_limit' => ['limit' => 2000, 'remaining' => 0, 'reset_time' => 1762946810, 'retry_after' => 0],
    ], 429)]);

    expect(fn () => $eppo->taxon('BEMITA')->overview())->toThrow(RateLimitException::class);

    Http::assertSentCount(2);
});

it('recovers when a retried request succeeds', function (): void {
    $eppo = reconfigure(['eppo.retry.times' => 3]);

    Http::fake(['*' => Http::sequence()
        ->push(['error' => 'boom'], 500)
        ->push(Responses::taxonOverview(), 200)]);

    expect($eppo->taxon('BEMITA')->overview()->prefname)->toBe('Bemisia tabaci');

    Http::assertSentCount(2);
});

it('refuses to call the API without a key', function (): void {
    $eppo = reconfigure(['eppo.key' => null]);
    Http::fake();

    expect(fn () => $eppo->taxon('BEMITA')->overview())
        ->toThrow(ConfigurationException::class);

    Http::assertNothingSent();
});
