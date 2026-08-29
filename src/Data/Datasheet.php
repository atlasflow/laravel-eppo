<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Illuminate\Support\Collection;

/**
 * Everything EPPO holds about one taxon, gathered into a single record.
 *
 * EPPO writes prose datasheets for its quarantine pests, but API v2 does not
 * expose them — `/infos` reports `datasheet: 1` and there is no route to fetch
 * it. This is the assembled equivalent: the same ground covered by the
 * endpoints that are exposed.
 *
 * Sections EPPO has no records for come back as empty collections and cost no
 * request; see TaxonResource::datasheet().
 */
final readonly class Datasheet
{
    /**
     * Every section this record can carry, in the order a reader wants them.
     */
    public const SECTIONS = [
        'taxonomy',
        'names',
        'categorization',
        'distribution',
        'hosts',
        'pests',
        'vectors',
        'vectorof',
        'bca',
        'bcaof',
        'standards',
        'documents',
        'photos',
        'reporting',
    ];

    /**
     * @param  Collection<int, TaxonomyItem>  $taxonomy
     * @param  Collection<int, TaxonName>  $names
     * @param  Collection<int, Categorization>  $categorization
     * @param  Collection<int, Distribution>  $distribution
     * @param  Collection<int, HostPest>  $hosts
     * @param  Collection<int, HostPest>  $pests
     * @param  Collection<int, Vector>  $vectors
     * @param  Collection<int, Vector>  $vectorOf
     * @param  Collection<int, BiologicalControlAgent>  $biologicalControlAgents
     * @param  Collection<int, BiologicalControlAgent>  $biologicalControlAgentOf
     * @param  Collection<int, Standard>  $standards
     * @param  Collection<int, Document>  $documents
     * @param  Collection<int, Photo>  $photos
     * @param  Collection<int, ReportingArticle>  $reportingArticles
     * @param  list<string>  $fetched  sections that actually cost a request
     */
    public function __construct(
        public Taxon $taxon,
        public TaxonInfos $infos,
        public Collection $taxonomy,
        public Collection $names,
        public Collection $categorization,
        public Collection $distribution,
        public Collection $hosts,
        public Collection $pests,
        public Collection $vectors,
        public Collection $vectorOf,
        public Collection $biologicalControlAgents,
        public Collection $biologicalControlAgentOf,
        public Collection $standards,
        public Collection $documents,
        public Collection $photos,
        public Collection $reportingArticles,
        public array $fetched = [],
    ) {}

    public function eppocode(): string
    {
        return $this->taxon->eppocode;
    }

    public function name(): string
    {
        return $this->taxon->prefname;
    }

    /**
     * The kingdom, from the taxonomy chain rather than a second request.
     */
    public function kingdom(): ?string
    {
        return $this->taxonomy->firstWhere('type', 'Kingdom')?->prefname;
    }

    /**
     * The rank of the taxon itself — "Species", "Genus", "Family".
     */
    public function rank(): ?string
    {
        return $this->taxonomy->last()?->type;
    }

    /**
     * @return Collection<string, Collection<int, TaxonName>>
     */
    public function namesByLanguage(): Collection
    {
        return $this->names->groupBy('langIso');
    }

    /**
     * Hosts grouped by classification, commonest group first.
     *
     * @return Collection<string, Collection<int, HostPest>>
     */
    public function hostsByClass(): Collection
    {
        return $this->hosts
            ->groupBy(fn (HostPest $host): string => $host->classLabel ?? 'Unclassified')
            ->sortByDesc(fn (Collection $group): int => $group->count());
    }

    /**
     * @return Collection<int, HostPest>
     */
    public function majorHosts(): Collection
    {
        return $this->hosts->filter(
            fn (HostPest $host): bool => $host->classLabel !== null && str_contains($host->classLabel, 'Major')
        )->values();
    }

    /**
     * Distribution grouped by raw status code. Resolve the codes to labels with
     * `Eppo::references()->distributionStatuses()`.
     *
     * @return Collection<string, Collection<int, Distribution>>
     */
    public function distributionByStatus(): Collection
    {
        return $this->distribution
            ->groupBy('pestStatus')
            ->sortByDesc(fn (Collection $group): int => $group->count());
    }

    /**
     * @return Collection<int, string>
     */
    public function countries(): Collection
    {
        return $this->distribution->pluck('countryIso')->unique()->sort()->values();
    }

    /**
     * Listings that have not been withdrawn, grouped by EPPO list.
     *
     * @return Collection<string, Collection<int, Categorization>>
     */
    public function currentListings(): Collection
    {
        return $this->categorization
            ->filter(fn (Categorization $entry): bool => $entry->isCurrent())
            ->groupBy('qlistLabel');
    }

    /**
     * Sections that came back with something in them.
     *
     * @return list<string>
     */
    public function sections(): array
    {
        return array_values(array_filter(
            self::SECTIONS,
            fn (string $section): bool => $this->has($section),
        ));
    }

    public function has(string $section): bool
    {
        return $this->section($section)?->isNotEmpty() ?? false;
    }

    /**
     * @return Collection<array-key, mixed>|null
     */
    public function section(string $section): ?Collection
    {
        return match ($section) {
            'taxonomy' => $this->taxonomy,
            'names' => $this->names,
            'categorization' => $this->categorization,
            'distribution' => $this->distribution,
            'hosts' => $this->hosts,
            'pests' => $this->pests,
            'vectors' => $this->vectors,
            'vectorof' => $this->vectorOf,
            'bca' => $this->biologicalControlAgents,
            'bcaof' => $this->biologicalControlAgentOf,
            'standards' => $this->standards,
            'documents' => $this->documents,
            'photos' => $this->photos,
            'reporting' => $this->reportingArticles,
            default => null,
        };
    }

    /**
     * How many records each section holds.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (self::SECTIONS as $section) {
            $counts[$section] = $this->section($section)?->count() ?? 0;
        }

        return $counts;
    }
}
