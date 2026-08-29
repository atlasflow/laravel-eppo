<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * A state or province within a country.
 */
final readonly class CountryState
{
    public function __construct(
        public string $stateId,
        public string $stateName,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            stateId: Cast::string($data, 'state_id') !== '' ? Cast::string($data, 'state_id') : Cast::string($data, 'state_code'),
            stateName: Cast::string($data, 'state_name'),
        );
    }
}
