<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * A biological control agent link, in either direction.
 */
final readonly class BiologicalControlAgent
{
    public function __construct(
        public string $eppocode,
        public string $prefname,
        public ?string $bibref,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eppocode: Cast::string($data, 'eppocode'),
            prefname: Cast::string($data, 'prefname'),
            bibref: Cast::nullableString($data, 'bibref'),
        );
    }
}
