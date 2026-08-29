<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * One taxon's listing status for a country or RPPO.
 */
final readonly class CountryCategorization
{
    public function __construct(
        public string $eppocode,
        public string $prefname,
        public string $qlist,
        public string $qlistLabel,
        public ?int $yearAdded,
        public ?int $yearDeleted,
        public ?int $yearTransient,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eppocode: Cast::string($data, 'eppocode'),
            prefname: Cast::string($data, 'prefname'),
            qlist: Cast::string($data, 'qlist'),
            qlistLabel: Cast::string($data, 'qlist_label'),
            yearAdded: Cast::nullableInt($data, 'year_add'),
            yearDeleted: Cast::nullableInt($data, 'year_del'),
            yearTransient: Cast::nullableInt($data, 'year_transient'),
        );
    }
}
