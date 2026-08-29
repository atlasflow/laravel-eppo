<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Cache;

use Atlasflow\Eppo\Http\Endpoint;
use DateTimeImmutable;

/**
 * One stored EPPO response.
 *
 * Two independent clocks: `staleAt` (when the copy should be revalidated) and
 * `expiresAt` (when the row may be deleted). Durable entries normally have a
 * `staleAt` and a null `expiresAt` — revalidate, never evict.
 */
final class CacheEntry
{
    /**
     * @param  array<array-key, mixed>|null  $payload  null means a cached 404
     */
    public function __construct(
        public readonly string $key,
        public readonly Endpoint $endpoint,
        public readonly ?array $payload,
        public readonly int $status,
        public readonly string $version,
        public readonly DateTimeImmutable $fetchedAt,
        public readonly ?DateTimeImmutable $staleAt = null,
        public readonly ?DateTimeImmutable $expiresAt = null,
        public readonly int $hits = 0,
        public readonly ?string $payloadHash = null,
    ) {}

    public function isStale(?DateTimeImmutable $now = null): bool
    {
        if ($this->staleAt === null) {
            return false;
        }

        return ($now ?? new DateTimeImmutable) >= $this->staleAt;
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return ($now ?? new DateTimeImmutable) >= $this->expiresAt;
    }

    public function isNegative(): bool
    {
        return $this->status === 404;
    }

    public function hash(): string
    {
        return $this->payloadHash ?? self::hashPayload($this->payload);
    }

    /**
     * @param  array<array-key, mixed>|null  $payload
     */
    public static function hashPayload(?array $payload): string
    {
        return sha1((string) json_encode($payload));
    }
}
