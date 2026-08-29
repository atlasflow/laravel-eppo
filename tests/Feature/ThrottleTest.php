<?php

declare(strict_types=1);

use Atlasflow\Eppo\Exceptions\ThrottleException;
use Atlasflow\Eppo\Http\Throttle;
use Atlasflow\Eppo\Tests\Fixtures\Responses;
use Illuminate\Support\Facades\Http;

it('counts requests against the shared window', function (): void {
    refake(['*' => Http::response(Responses::search())], [
        'eppo.throttle.enabled' => true,
        'eppo.throttle.max_requests' => 50,
        'eppo.cache.enabled' => false,
    ]);

    eppo()->tools()->search('Bemisia');
    eppo()->tools()->search('Bemisia');

    $window = (int) floor(microtime(true) / 10);

    expect((int) cache()->get('eppo:throttle:'.$window))->toBe(2);
});

it('refuses rather than waiting forever once the window is full', function (): void {
    $throttle = new Throttle(cache()->store('array'), maxRequests: 1, windowSeconds: 10, maxWaitSeconds: 0);

    $throttle->acquire();

    expect(fn () => $throttle->acquire())->toThrow(ThrottleException::class);
});

it('stays out of the way when disabled', function (): void {
    refake(['*' => Http::response(Responses::search())], ['eppo.throttle.enabled' => false]);

    eppo()->tools()->search('Bemisia');

    $window = (int) floor(microtime(true) / 10);

    expect(cache()->get('eppo:throttle:'.$window))->toBeNull();
});
