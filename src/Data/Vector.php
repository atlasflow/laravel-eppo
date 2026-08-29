<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * An organism that transmits another, or is transmitted by it.
 */
final readonly class Vector
{
    public function __construct(
        public string $eppocode,
        public string $prefname,
        public ?string $vectorClassId,
        public ?string $vectorClassLabel,
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
            vectorClassId: Cast::nullableString($data, 'vectorclass_id'),
            vectorClassLabel: Cast::nullableString($data, 'vectorclass_label'),
            bibref: Cast::nullableString($data, 'bibref'),
        );
    }
}
