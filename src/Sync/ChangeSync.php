<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Sync;

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Data\TaxonListItem;
use Atlasflow\Eppo\Eppo;
use Atlasflow\Eppo\Events\TaxonChanged;
use Atlasflow\Eppo\Exceptions\EppoException;
use Atlasflow\Eppo\Http\Endpoint;
use Atlasflow\Eppo\Support\Emit;
use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Change-driven cache invalidation.
 *
 * The naive way to keep a long-lived cache honest is to expire everything on a
 * timer, which throws away thousands of unchanged records to catch the handful
 * that moved. EPPO publishes a change feed instead — `/taxons/list` filtered by
 * `createdFromDate` / `updatedFromDate` — so this asks which codes actually
 * changed since the last run and invalidates only those.
 *
 * Run it nightly. A year of drift costs one pass over a few hundred codes.
 */
final class ChangeSync
{
    /**
     * @param  array<string, mixed>  $config  the `eppo.sync` block
     * @param  list<string>  $warmResources
     */
    public function __construct(
        private readonly Eppo $eppo,
        private readonly CacheManager $cache,
        private readonly SyncState $state,
        private readonly array $config = [],
        private readonly array $warmResources = [],
        private readonly ?Dispatcher $events = null,
    ) {}

    /**
     * @param  Closure(TaxonListItem, int): void|null  $onChange  progress callback
     */
    public function run(
        DateTimeImmutable|string|null $since = null,
        ?int $pageSize = null,
        ?bool $refresh = null,
        ?Closure $onChange = null,
    ): SyncResult {
        $ranAt = new DateTimeImmutable;
        $from = $this->resolveSince($since);
        $refresh ??= (bool) ($this->config['refresh'] ?? false);
        $pageSize ??= (int) ($this->config['page_size'] ?? 1000);

        $scanned = 0;
        $invalidated = 0;
        $refreshed = 0;
        $codes = [];

        // `fresh()` matters here: reading the change feed from cache would
        // report the same changes on every run.
        foreach ($this->eppo->fresh()->taxons()->changedSince($from, $pageSize) as $taxon) {
            $scanned++;

            $endpoints = $this->endpointsFor($taxon->eppocode);
            $removed = $this->cache->forgetSubject($taxon->eppocode);

            $invalidated += $removed;
            $codes[] = $taxon->eppocode;

            if ($refresh) {
                $refreshed += $this->rewarm($endpoints);
            }

            Emit::event(new TaxonChanged($taxon, $removed), $this->events);

            $onChange?->__invoke($taxon, $removed);
        }

        $result = new SyncResult(
            since: $from->format('Y-m-d'),
            scanned: $scanned,
            invalidatedEntries: $invalidated,
            refreshed: $refreshed,
            ranAt: $ranAt,
            codes: $codes,
        );

        $this->state->record($result);

        return $result;
    }

    /**
     * Where the next run should start: the last recorded run minus the
     * configured overlap, so a change EPPO backdates slightly is not skipped.
     */
    public function resolveSince(DateTimeImmutable|string|null $since = null): DateTimeImmutable
    {
        if ($since instanceof DateTimeImmutable) {
            return $since;
        }

        if (is_string($since) && $since !== '') {
            return new DateTimeImmutable($since);
        }

        $last = $this->state->lastChangeDate();

        if ($last === null) {
            return new DateTimeImmutable((string) ($this->config['initial_since'] ?? '-1 year'));
        }

        $overlap = (int) ($this->config['overlap_days'] ?? 2);

        return $overlap > 0 ? $last->modify(sprintf('-%d days', $overlap)) : $last;
    }

    /**
     * The cached endpoints for a code, captured before invalidation so they can
     * be re-fetched afterwards.
     *
     * @return list<Endpoint>
     */
    private function endpointsFor(string $code): array
    {
        $endpoints = [];

        foreach ($this->cache->durable()->endpoints(subject: $code) as $endpoint) {
            $endpoints[] = $endpoint;
        }

        if ($endpoints !== []) {
            return $endpoints;
        }

        // Nothing was cached for this code yet; warm the configured defaults.
        return array_map(
            fn (string $segment): Endpoint => Endpoint::make(
                sprintf('/taxons/taxon/%s/%s', $code, $segment),
                'taxon.'.$segment,
                $code,
            ),
            $this->warmResources,
        );
    }

    /**
     * @param  list<Endpoint>  $endpoints
     */
    private function rewarm(array $endpoints): int
    {
        $count = 0;

        foreach ($endpoints as $endpoint) {
            try {
                $this->cache->refresh($endpoint);
                $count++;
            } catch (EppoException) {
                // A code that vanished upstream is expected during a sync;
                // leaving the hole is correct, the next read will settle it.
            }
        }

        return $count;
    }
}
