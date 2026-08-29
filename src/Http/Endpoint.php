<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Http;

/**
 * One addressable EPPO read: where it lives, what it is, and what it is about.
 *
 * `resource` ("taxon.hosts") drives TTL lookup and resource-wide invalidation.
 * `subject` ("BEMITA") is what `eppo:sync` invalidates when EPPO reports that a
 * code changed. Both are stored alongside the payload so a cached row can be
 * refreshed later without the calling code being present.
 *
 * `ephemeral` marks a read that must never be cached — the change feed the sync
 * walks, whose whole job is to tell us what the cache has got wrong.
 */
final readonly class Endpoint implements \JsonSerializable
{
    /**
     * @param  array<string, scalar|null>  $query
     */
    public function __construct(
        public string $path,
        public string $resource,
        public ?string $subject = null,
        public array $query = [],
        public bool $ephemeral = false,
    ) {}

    /**
     * @param  array<string, scalar|null>  $query
     */
    public static function make(
        string $path,
        string $resource,
        ?string $subject = null,
        array $query = [],
        bool $ephemeral = false,
    ): self {
        return new self($path, $resource, $subject, self::normalizeQuery($query), $ephemeral);
    }

    public function ephemeral(): self
    {
        return new self($this->path, $this->resource, $this->subject, $this->query, ephemeral: true);
    }

    /**
     * Drop nulls and sort, so `?a=1&b=2` and `?b=2&a=1` are one cache entry.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, scalar>
     */
    public static function normalizeQuery(array $query): array
    {
        $normalized = [];

        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalized[$key] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }

        ksort($normalized);

        return $normalized;
    }

    public function url(string $baseUrl): string
    {
        $url = rtrim($baseUrl, '/').'/'.ltrim($this->path, '/');

        return $this->query === [] ? $url : $url.'?'.http_build_query($this->query);
    }

    /**
     * Stable identity for this read, independent of cache version.
     */
    public function signature(): string
    {
        return $this->query === []
            ? $this->path
            : $this->path.'?'.http_build_query($this->query);
    }

    /**
     * @return array{path: string, resource: string, subject: ?string, query: array<string, scalar>, ephemeral: bool}
     */
    public function jsonSerialize(): array
    {
        return [
            'path' => $this->path,
            'resource' => $this->resource,
            'subject' => $this->subject,
            'query' => $this->query,
            'ephemeral' => $this->ephemeral,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['path'] ?? ''),
            (string) ($data['resource'] ?? ''),
            isset($data['subject']) ? (string) $data['subject'] : null,
            is_array($data['query'] ?? null) ? $data['query'] : [],
            (bool) ($data['ephemeral'] ?? false),
        );
    }
}
