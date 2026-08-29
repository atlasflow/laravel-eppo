<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * One rung of a taxon's classification, kingdom downwards.
 */
final readonly class TaxonomyItem
{
    public function __construct(
        public string $eppocode,
        public string $prefname,
        public int $level,
        public string $type,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eppocode: Cast::string($data, 'eppocode'),
            prefname: Cast::string($data, 'prefname'),
            level: Cast::int($data, 'level'),
            type: Cast::string($data, 'type'),
        );
    }
}
