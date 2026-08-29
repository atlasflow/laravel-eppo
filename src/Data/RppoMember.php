<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * A country belonging to a Regional Plant Protection Organization.
 */
final readonly class RppoMember
{
    public function __construct(
        public string $isocode,
        public string $country,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isocode: Cast::string($data, 'isocode'),
            country: Cast::string($data, 'country'),
        );
    }
}
