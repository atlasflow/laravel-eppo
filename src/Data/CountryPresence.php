<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * One pest recorded in a country.
 */
final readonly class CountryPresence
{
    public function __construct(
        public string $eppocode,
        public string $prefname,
        public ?string $stateId,
        public string $pestStatus,
        public ?string $yearSituation,
        public ?string $yearIntroduced,
        public ?string $yearEradicated,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eppocode: Cast::string($data, 'eppocode'),
            prefname: Cast::string($data, 'prefname'),
            stateId: Cast::nullableString($data, 'state_id'),
            pestStatus: Cast::string($data, 'peststatus'),
            yearSituation: Cast::nullableString($data, 'yr_situation'),
            yearIntroduced: Cast::nullableString($data, 'yr_introd'),
            yearEradicated: Cast::nullableString($data, 'yr_erad'),
        );
    }
}
