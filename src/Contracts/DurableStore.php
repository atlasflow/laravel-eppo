<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Contracts;

use Atlasflow\Eppo\Cache\CacheEntry;
use Atlasflow\Eppo\Http\Endpoint;

/**
 * Long-term storage for EPPO responses. Implementations are expected to keep
 * entries indefinitely unless asked otherwise — expiry here is advisory.
 */
interface DurableStore
{
    public function get(string $key): ?CacheEntry;

    public function put(CacheEntry $entry): void;

    public function forget(string $key): bool;

    /**
     * Drop every entry whose subject matches, across all resources.
     * Returns the number of entries removed.
     */
    public function forgetSubject(string $subject): int;

    /**
     * Drop every entry for a resource identifier, `taxon.hosts` style.
     * A trailing `*` matches a group.
     */
    public function forgetResource(string $resource): int;

    /**
     * Remove everything, including entries from earlier cache versions.
     */
    public function flush(): int;

    /**
     * Remove hard-expired entries and entries left behind by an older
     * `cache.version`. Returns the number of entries removed.
     */
    public function prune(?string $currentVersion = null): int;

    public function recordHit(string $key): void;

    /**
     * @return array{entries: int, subjects: int, stale: int, bytes: int, oldest: ?string, newest: ?string}
     */
    public function stats(): array;

    /**
     * Iterate stored endpoints, optionally narrowed to a resource (a trailing
     * `*` matches a group) and/or a subject. Used to expire the matching L1
     * keys before a bulk delete, and by the warm/refresh commands.
     *
     * @return iterable<Endpoint>
     */
    public function endpoints(?string $resource = null, ?string $subject = null): iterable;

    /**
     * Distinct subjects present in the store.
     *
     * @return iterable<string>
     */
    public function subjects(): iterable;
}
