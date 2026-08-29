<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * A vector classification, e.g. "Known vector".
 */
final readonly class VectorClassification
{
    public function __construct(
        public string $vectorClassId,
        public string $label,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            vectorClassId: Cast::string($data, 'vectorclass_id'),
            label: Cast::string($data, 'vectorclass_label'),
        );
    }
}
