<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Resources;

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Http\Endpoint;
use Closure;
use Illuminate\Support\Collection;

abstract class Resource
{
    public function __construct(
        protected readonly CacheManager $cache,
        protected readonly bool $fresh = false,
    ) {}

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<array-key, mixed>
     */
    protected function get(
        string $path,
        string $resource,
        ?string $subject = null,
        array $query = [],
        bool $ephemeral = false,
    ): array {
        return $this->cache->get(Endpoint::make($path, $resource, $subject, $query, $ephemeral), $this->fresh);
    }

    /**
     * Map a list response onto DTOs. Pass the hydrator as a first-class
     * callable: `$this->collect($rows, TaxonName::fromArray(...))`.
     *
     * @template T of object
     *
     * @param  array<array-key, mixed>  $rows
     * @param  Closure(array<array-key, mixed>): T  $make
     * @return Collection<int, T>
     */
    protected function collect(array $rows, Closure $make): Collection
    {
        return (new Collection($rows))
            ->map(fn (mixed $row): object => $make(is_array($row) ? $row : []))
            ->values();
    }
}
