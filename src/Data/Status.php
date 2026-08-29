<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * Health of the EPPO API, straight from /status.
 */
final readonly class Status
{
    public function __construct(
        public string $status,
        public int $timestamp,
        public string $version,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: Cast::string($data, 'status'),
            timestamp: Cast::int($data, 'timestamp'),
            version: Cast::string($data, 'version'),
        );
    }
}
