<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Cache;

use Atlasflow\Eppo\Contracts\DurableStore;

/**
 * Used when `eppo.cache.durable.enabled` is false — every read goes upstream.
 */
final class NullStore implements DurableStore
{
    public function get(string $key): ?CacheEntry
    {
        return null;
    }

    public function put(CacheEntry $entry): void {}

    public function forget(string $key): bool
    {
        return false;
    }

    public function forgetSubject(string $subject): int
    {
        return 0;
    }

    public function forgetResource(string $resource): int
    {
        return 0;
    }

    public function flush(): int
    {
        return 0;
    }

    public function prune(?string $currentVersion = null): int
    {
        return 0;
    }

    public function recordHit(string $key): void {}

    public function stats(): array
    {
        return ['entries' => 0, 'subjects' => 0, 'stale' => 0, 'bytes' => 0, 'oldest' => null, 'newest' => null];
    }

    public function endpoints(?string $resource = null, ?string $subject = null): iterable
    {
        return [];
    }

    /**
     * @return iterable<string>
     */
    public function subjects(): iterable
    {
        return [];
    }
}
