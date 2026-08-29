<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * Where a pest is recorded, and with what status.
 */
final readonly class Distribution
{
    public function __construct(
        public string $countryIso,
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
            countryIso: Cast::string($data, 'country_iso'),
            stateId: Cast::nullableString($data, 'state_id'),
            pestStatus: Cast::string($data, 'peststatus'),
            yearSituation: Cast::nullableString($data, 'yr_situation'),
            yearIntroduced: Cast::nullableString($data, 'yr_introd'),
            yearEradicated: Cast::nullableString($data, 'yr_erad'),
        );
    }
}
