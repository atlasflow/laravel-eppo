<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * An EPPO quarantine or categorization list.
 */
final readonly class QList
{
    public function __construct(
        public string $qlist,
        public string $label,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            qlist: Cast::string($data, 'qlist'),
            label: Cast::string($data, 'qlist_label'),
        );
    }
}
