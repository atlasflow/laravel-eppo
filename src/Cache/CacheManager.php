<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Cache;

use Atlasflow\Eppo\Contracts\DurableStore;
use Atlasflow\Eppo\Contracts\Transport;
use Atlasflow\Eppo\Events\CacheHit;
use Atlasflow\Eppo\Events\CacheMissed;
use Atlasflow\Eppo\Events\EntryInvalidated;
use Atlasflow\Eppo\Events\EntryStored;
use Atlasflow\Eppo\Events\StaleEntryServed;
use Atlasflow\Eppo\Exceptions\EppoException;
use Atlasflow\Eppo\Exceptions\MissingRecord;
use Atlasflow\Eppo\Exceptions\NotFoundException;
use Atlasflow\Eppo\Http\Endpoint;
use Atlasflow\Eppo\Jobs\RefreshCacheEntry;
use Atlasflow\Eppo\Support\Emit;
use DateTimeImmutable;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;

/**
 * Everything between a caller and the network.
 *
 * The read path is: L1 → durable → EPPO. The write path stores into both. The
 * interesting behaviour is what happens in the middle — a durable entry past
 * its stale time is still returned to the caller, and revalidated out of band.
 */
final class CacheManager
{
    /**
     * @param  array<string, mixed>  $config  the `eppo.cache` block
     */
    public function __construct(
        private readonly Transport $transport,
        private readonly DurableStore $durable,
        private readonly ?Repository $l1,
        private readonly array $config,
        private readonly ?EventDispatcher $events = null,
        private readonly ?BusDispatcher $bus = null,
    ) {}

    /**
     * Read through the cache. Returns the decoded payload.
     *
     * @return array<array-key, mixed>
     *
     * @throws EppoException
     */
    public function get(Endpoint $endpoint, bool $fresh = false): array
    {
        if (! $this->enabled() || $endpoint->ephemeral) {
            return $this->fetchAndStore($endpoint, store: false);
        }

        $key = $this->keyFor($endpoint);
        $stale = null;

        if (! $fresh) {
            if (($hit = $this->readL1($key)) !== null) {
                $this->emit(new CacheHit($endpoint, 'l1'));

                return $this->unwrap($hit, $endpoint);
            }

            $entry = $this->durable->get($key);

            if ($entry !== null && ! $entry->isExpired()) {
                if (! $entry->isStale()) {
                    $this->durable->recordHit($key);
                    $this->writeL1($key, $entry);
                    $this->emit(new CacheHit($endpoint, 'durable'));

                    return $this->unwrap($this->wrap($entry), $endpoint);
                }

                if ($this->revalidatesInBackground()) {
                    $this->queueRevalidation($endpoint, $key);
                    $this->durable->recordHit($key);
                    $this->writeL1($key, $entry, $this->staleL1Ttl());
                    $this->emit(new StaleEntryServed($endpoint, revalidating: true));

                    return $this->unwrap($this->wrap($entry), $endpoint);
                }

                // Blocking revalidation: keep the stale copy as a safety net.
                $stale = $entry;
            }
        }

        $this->emit(new CacheMissed($endpoint));

        try {
            return $this->fetchAndStore($endpoint);
        } catch (EppoException $e) {
            if ($stale !== null && $this->servesStaleOnError() && ! $e instanceof MissingRecord) {
                $this->emit(new StaleEntryServed($endpoint, revalidating: false, becauseOfError: true));

                return $this->unwrap($this->wrap($stale), $endpoint);
            }

            throw $e;
        }
    }

    /**
     * Fetch from EPPO regardless of what is cached, and replace the entry.
     *
     * @return array<array-key, mixed>
     */
    public function refresh(Endpoint $endpoint): array
    {
        return $this->get($endpoint, fresh: true);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function fetchAndStore(Endpoint $endpoint, bool $store = true): array
    {
        try {
            $payload = $this->transport->get($endpoint);
        } catch (MissingRecord $e) {
            if ($store && $this->enabled() && $this->cachesMisses()) {
                $this->store($endpoint, null, 404);
            }

            throw $e;
        }

        if ($store && $this->enabled()) {
            $this->store($endpoint, $payload, 200);
        }

        return $payload;
    }

    /**
     * @param  array<array-key, mixed>|null  $payload
     */
    private function store(Endpoint $endpoint, ?array $payload, int $status): CacheEntry
    {
        $key = $this->keyFor($endpoint);
        $now = new DateTimeImmutable;

        $ttl = $status === 404
            ? $this->ttlFor('negative')
            : $this->ttlFor($endpoint->resource);

        $keepFor = $this->config['keep_for'] ?? null;

        $existing = $this->durable->get($key);

        $entry = new CacheEntry(
            key: $key,
            endpoint: $endpoint,
            payload: $payload,
            status: $status,
            version: $this->version(),
            fetchedAt: $now,
            staleAt: $ttl === null ? null : $now->modify(sprintf('+%d seconds', $ttl)),
            expiresAt: $keepFor === null ? null : $now->modify(sprintf('+%d seconds', (int) $keepFor)),
            hits: $existing->hits ?? 0,
            payloadHash: CacheEntry::hashPayload($payload),
        );

        $this->durable->put($entry);
        $this->writeL1($key, $entry);

        $this->emit(new EntryStored(
            $endpoint,
            changed: $existing === null || $existing->hash() !== $entry->hash(),
        ));

        return $entry;
    }

    // -----------------------------------------------------------------
    // Invalidation
    // -----------------------------------------------------------------

    public function forget(Endpoint $endpoint): bool
    {
        $key = $this->keyFor($endpoint);

        $this->l1?->forget($this->l1Key($key));

        $removed = $this->durable->forget($key);

        $this->emit(new EntryInvalidated('endpoint', $endpoint->signature(), $removed ? 1 : 0));

        return $removed;
    }

    /**
     * Bust every cached resource for one EPPO code, country or RPPO. This is
     * the granular bust `eppo:sync` uses, and almost always the one you want.
     */
    public function forgetSubject(string $subject): int
    {
        $this->forgetL1For($this->durable->endpoints(subject: $subject));

        $count = $this->durable->forgetSubject($subject);

        $this->emit(new EntryInvalidated('subject', $subject, $count));

        return $count;
    }

    /**
     * Bust a resource across every subject — `taxon.distribution`, or
     * `references.*` for a whole group.
     */
    public function forgetResource(string $resource): int
    {
        $this->forgetL1For($this->durable->endpoints($resource));

        $count = $this->durable->forgetResource($resource);

        $this->emit(new EntryInvalidated('resource', $resource, $count));

        return $count;
    }

    public function flush(): int
    {
        $this->l1?->clear();

        $count = $this->durable->flush();

        $this->emit(new EntryInvalidated('all', '*', $count));

        return $count;
    }

    public function prune(): int
    {
        $count = $this->durable->prune($this->version());

        $this->emit(new EntryInvalidated('prune', $this->version(), $count));

        return $count;
    }

    /**
     * @return array{entries: int, subjects: int, stale: int, bytes: int, oldest: ?string, newest: ?string}
     */
    public function stats(): array
    {
        return $this->durable->stats();
    }

    public function durable(): DurableStore
    {
        return $this->durable;
    }

    // -----------------------------------------------------------------
    // TTL resolution
    // -----------------------------------------------------------------

    /**
     * Exact match wins, then the nearest `group.*` wildcard, then `default`.
     * A configured `null` means "never goes stale".
     */
    public function ttlFor(string $resource): ?int
    {
        /** @var array<string, int|null> $map */
        $map = $this->config['ttl'] ?? [];

        if (array_key_exists($resource, $map)) {
            return $map[$resource] === null ? null : (int) $map[$resource];
        }

        $segments = explode('.', $resource);

        for ($i = count($segments) - 1; $i > 0; $i--) {
            $wildcard = implode('.', array_slice($segments, 0, $i)).'.*';

            if (array_key_exists($wildcard, $map)) {
                return $map[$wildcard] === null ? null : (int) $map[$wildcard];
            }
        }

        if (array_key_exists('default', $map)) {
            return $map['default'] === null ? null : (int) $map['default'];
        }

        return Ttl::days(90);
    }

    public function keyFor(Endpoint $endpoint): string
    {
        return CacheKey::for($endpoint, $this->version());
    }

    public function version(): string
    {
        return (string) ($this->config['version'] ?? 'v1');
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    // -----------------------------------------------------------------
    // Tier 1
    // -----------------------------------------------------------------

    /**
     * @return array{status: int, payload: array<array-key, mixed>|null}|null
     */
    private function readL1(string $key): ?array
    {
        if ($this->l1 === null || ! ($this->config['l1']['enabled'] ?? true)) {
            return null;
        }

        $value = $this->l1->get($this->l1Key($key));

        return is_array($value) && array_key_exists('status', $value) ? $value : null;
    }

    private function writeL1(string $key, CacheEntry $entry, ?int $ttl = null): void
    {
        if ($this->l1 === null || ! ($this->config['l1']['enabled'] ?? true)) {
            return;
        }

        $this->l1->put($this->l1Key($key), $this->wrap($entry), $ttl ?? (int) ($this->config['l1']['ttl'] ?? 3600));
    }

    /**
     * @param  iterable<Endpoint>  $endpoints
     */
    private function forgetL1For(iterable $endpoints): void
    {
        if ($this->l1 === null) {
            return;
        }

        foreach ($endpoints as $endpoint) {
            $this->l1->forget($this->l1Key($this->keyFor($endpoint)));
        }
    }

    private function l1Key(string $key): string
    {
        return ($this->config['l1']['prefix'] ?? 'eppo').':'.$key;
    }

    /**
     * @return array{status: int, payload: array<array-key, mixed>|null}
     */
    private function wrap(CacheEntry $entry): array
    {
        return ['status' => $entry->status, 'payload' => $entry->payload];
    }

    /**
     * @param  array{status: int, payload: array<array-key, mixed>|null}  $wrapped
     * @return array<array-key, mixed>
     */
    private function unwrap(array $wrapped, Endpoint $endpoint): array
    {
        if ($wrapped['status'] === 404) {
            throw new NotFoundException(
                sprintf('EPPO has no record at %s (cached absence).', $endpoint->signature()),
                404,
                $endpoint->path,
            );
        }

        return $wrapped['payload'] ?? [];
    }

    // -----------------------------------------------------------------
    // Revalidation
    // -----------------------------------------------------------------

    private function revalidatesInBackground(): bool
    {
        return (bool) ($this->config['revalidate']['enabled'] ?? true) && $this->bus !== null;
    }

    private function queueRevalidation(Endpoint $endpoint, string $key): void
    {
        // One refresh per stale entry per lock window, however many readers
        // arrive at once.
        if ($this->l1 !== null) {
            $lock = $this->l1Key('revalidating:'.$key);
            $seconds = (int) ($this->config['revalidate']['lock_seconds'] ?? 60);

            if (! $this->l1->add($lock, 1, $seconds)) {
                return;
            }
        }

        $job = new RefreshCacheEntry($endpoint);

        $connection = $this->config['revalidate']['connection'] ?? null;
        $queue = $this->config['revalidate']['queue'] ?? null;

        if ($connection !== null) {
            $job->onConnection((string) $connection);
        }

        if ($queue !== null) {
            $job->onQueue((string) $queue);
        }

        $this->bus?->dispatch($job);
    }

    private function staleL1Ttl(): int
    {
        return min(60, (int) ($this->config['l1']['ttl'] ?? 3600));
    }

    private function servesStaleOnError(): bool
    {
        return (bool) ($this->config['serve_stale_on_error'] ?? true);
    }

    private function emit(object $event): void
    {
        Emit::event($event, $this->events);
    }

    private function cachesMisses(): bool
    {
        return (bool) ($this->config['durable']['cache_misses'] ?? true);
    }
}
