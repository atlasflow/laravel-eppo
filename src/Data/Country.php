<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;
use Illuminate\Support\Collection;

/**
 * A country, with its subdivisions where EPPO tracks them.
 */
final readonly class Country
{
    /**
     * @param  Collection<int, CountryState>  $states
     */
    public function __construct(
        public string $countryIso,
        public string $countryName,
        public ?int $continentId,
        public ?string $continentName,
        public Collection $states,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            countryIso: Cast::string($data, 'country_iso'),
            countryName: Cast::string($data, 'country_name'),
            continentId: Cast::nullableInt($data, 'continent_id'),
            continentName: Cast::nullableString($data, 'continent_name'),
            states: (new Collection(Cast::arr($data, 'states')))
                ->map(fn (mixed $state): CountryState => CountryState::fromArray(is_array($state) ? $state : []))
                ->values(),
        );
    }
}
