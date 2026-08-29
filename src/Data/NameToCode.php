<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * An EPPO code matched from a name.
 */
final readonly class NameToCode
{
    public function __construct(
        public string $eppocode,
        public bool $preferred,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eppocode: Cast::string($data, 'eppocode'),
            preferred: Cast::bool($data, 'preferred'),
        );
    }
}
