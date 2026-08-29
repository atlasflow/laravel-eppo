<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Resources;

use Atlasflow\Eppo\Data\Country;
use Atlasflow\Eppo\Data\CountryState;
use Atlasflow\Eppo\Data\DistributionStatus;
use Atlasflow\Eppo\Data\PestHostClassification;
use Atlasflow\Eppo\Data\QList;
use Atlasflow\Eppo\Data\Rppo;
use Atlasflow\Eppo\Data\VectorClassification;
use Illuminate\Support\Collection;

/**
 * Lookup tables. These are the entries you most want durably cached — they
 * change perhaps once a year, and every other response references them.
 */
final class ReferencesResource extends Resource
{
    /**
     * @return Collection<int, Country>
     */
    public function countries(): Collection
    {
        return $this->collect($this->fetch('countries'), Country::fromArray(...));
    }

    /**
     * Subdivisions, keyed by ISO country code.
     *
     * @return Collection<string, Collection<int, CountryState>>
     */
    public function countriesStates(): Collection
    {
        return (new Collection($this->fetch('countriesStates')))
            ->map(fn (mixed $states): Collection => (new Collection(is_array($states) ? $states : []))
                ->map(fn (mixed $state): CountryState => CountryState::fromArray(is_array($state) ? $state : []))
                ->values());
    }

    /**
     * @return Collection<int, Rppo>
     */
    public function rppos(): Collection
    {
        return $this->collect($this->fetch('rppos'), Rppo::fromArray(...));
    }

    /**
     * The EPPO quarantine and categorization lists (A1, A2, …).
     *
     * @return Collection<int, QList>
     */
    public function qLists(): Collection
    {
        return $this->collect($this->fetch('qList', 'qlist'), QList::fromArray(...));
    }

    /**
     * @return Collection<int, DistributionStatus>
     */
    public function distributionStatuses(): Collection
    {
        return $this->collect($this->fetch('distributionStatus', 'distribution_status'), DistributionStatus::fromArray(...));
    }

    /**
     * @return Collection<int, PestHostClassification>
     */
    public function pestHostClassifications(): Collection
    {
        return $this->collect(
            $this->fetch('pestHostClassification', 'pest_host_classification'),
            PestHostClassification::fromArray(...),
        );
    }

    /**
     * @return Collection<int, VectorClassification>
     */
    public function vectorClassifications(): Collection
    {
        return $this->collect(
            $this->fetch('vectorClassification', 'vector_classification'),
            VectorClassification::fromArray(...),
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private function fetch(string $segment, ?string $resourceSuffix = null): array
    {
        return $this->get(
            '/references/'.$segment,
            'references.'.($resourceSuffix ?? $segment),
        );
    }
}
