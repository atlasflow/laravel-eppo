<?php

declare(strict_types=1);

namespace Atlasflow\Eppo;

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Data\Status;
use Atlasflow\Eppo\Http\Endpoint;
use Atlasflow\Eppo\Resources\CountryResource;
use Atlasflow\Eppo\Resources\ReferencesResource;
use Atlasflow\Eppo\Resources\ReportingsResource;
use Atlasflow\Eppo\Resources\RppoResource;
use Atlasflow\Eppo\Resources\TaxonResource;
use Atlasflow\Eppo\Resources\TaxonsResource;
use Atlasflow\Eppo\Resources\ToolsResource;

/**
 * Entry point for the EPPO Global Database.
 *
 *     Eppo::taxon('BEMITA')->overview()->prefname;   // Bemisia tabaci
 *     Eppo::tools()->codeFor('Bemisia tabaci');      // BEMITA
 *     Eppo::fresh()->taxon('BEMITA')->distribution() // ignore the cache once
 */
final class Eppo
{
    public function __construct(
        private readonly CacheManager $cache,
        private readonly bool $fresh = false,
    ) {}

    /**
     * A client that ignores cached copies and refreshes what it reads. Use it
     * for the rare case where you must see upstream right now; the result is
     * still written back to the cache for everyone else.
     */
    public function fresh(): self
    {
        return new self($this->cache, fresh: true);
    }

    public function taxon(string $eppocode): TaxonResource
    {
        return new TaxonResource($this->cache, $eppocode, $this->fresh);
    }

    public function taxons(): TaxonsResource
    {
        return new TaxonsResource($this->cache, $this->fresh);
    }

    public function country(string $isoCode): CountryResource
    {
        return new CountryResource($this->cache, $isoCode, $this->fresh);
    }

    public function rppo(string $rppoCode): RppoResource
    {
        return new RppoResource($this->cache, $rppoCode, $this->fresh);
    }

    public function reportings(): ReportingsResource
    {
        return new ReportingsResource($this->cache, $this->fresh);
    }

    public function references(): ReferencesResource
    {
        return new ReferencesResource($this->cache, $this->fresh);
    }

    public function tools(): ToolsResource
    {
        return new ToolsResource($this->cache, $this->fresh);
    }

    /**
     * API health. Cached for a minute, so a status page can call it freely.
     */
    public function status(): Status
    {
        return Status::fromArray(
            $this->cache->get(Endpoint::make('/status', 'status'), $this->fresh)
        );
    }

    /**
     * Call an endpoint this package does not wrap yet, with the same cache,
     * throttle and retry behaviour as everything else.
     *
     * @param  array<string, scalar|null>  $query
     * @return array<array-key, mixed>
     */
    public function raw(string $path, string $resource = 'raw', ?string $subject = null, array $query = []): array
    {
        return $this->cache->get(Endpoint::make($path, $resource, $subject, $query), $this->fresh);
    }

    /**
     * The cache itself: stats, warming, and every flavour of invalidation.
     */
    public function cache(): CacheManager
    {
        return $this->cache;
    }
}
