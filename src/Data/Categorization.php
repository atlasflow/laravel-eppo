<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * A taxon's regulatory standing in one country: which EPPO list it sits on
 * and when it was added, made transient, or removed.
 */
final readonly class Categorization
{
    public function __construct(
        public string $countryIso,
        public string $countryName,
        public int $continentId,
        public string $continentName,
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
            countryIso: Cast::string($data, 'country_iso'),
            countryName: Cast::string($data, 'country_name'),
            continentId: Cast::int($data, 'continent_id'),
            continentName: Cast::string($data, 'continent_name'),
            qlist: Cast::string($data, 'qlist'),
            qlistLabel: Cast::string($data, 'qlist_label'),
            yearAdded: Cast::nullableInt($data, 'year_add'),
            yearDeleted: Cast::nullableInt($data, 'year_delete'),
            yearTransient: Cast::nullableInt($data, 'year_transient'),
        );
    }

    /**
     * Still listed — added at some point and never removed.
     */
    public function isCurrent(): bool
    {
        return $this->yearDeleted === null;
    }
}
