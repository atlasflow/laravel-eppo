<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Resources;

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Data\BiologicalControlAgent;
use Atlasflow\Eppo\Data\Categorization;
use Atlasflow\Eppo\Data\Distribution;
use Atlasflow\Eppo\Data\Document;
use Atlasflow\Eppo\Data\HostPest;
use Atlasflow\Eppo\Data\Kingdom;
use Atlasflow\Eppo\Data\Photo;
use Atlasflow\Eppo\Data\ReportingArticle;
use Atlasflow\Eppo\Data\Standard;
use Atlasflow\Eppo\Data\Taxon;
use Atlasflow\Eppo\Data\TaxonInfos;
use Atlasflow\Eppo\Data\TaxonName;
use Atlasflow\Eppo\Data\TaxonomyItem;
use Atlasflow\Eppo\Data\Vector;
use Atlasflow\Eppo\Exceptions\NotFoundException;
use Atlasflow\Eppo\Support\Code;
use Illuminate\Support\Collection;

/**
 * Everything EPPO knows about one code.
 *
 * Each method is a separate cached endpoint, so pulling four facets of a taxon
 * costs four rows in the durable store and, after the first call, no network.
 */
final class TaxonResource extends Resource
{
    public readonly string $code;

    public function __construct(CacheManager $cache, string $code, bool $fresh = false)
    {
        parent::__construct($cache, $fresh);

        $this->code = Code::eppo($code);
    }

    public function overview(): Taxon
    {
        return Taxon::fromArray($this->fetch('overview'));
    }

    public function infos(): TaxonInfos
    {
        return TaxonInfos::fromArray($this->fetch('infos'));
    }

    /**
     * @return Collection<int, TaxonName>
     */
    public function names(): Collection
    {
        return $this->collect($this->fetch('names'), TaxonName::fromArray(...));
    }

    /**
     * The preferred scientific name, or null when EPPO lists none.
     */
    public function preferredName(): ?string
    {
        return $this->names()->firstWhere('preferred', true)?->fullname;
    }

    /**
     * @return Collection<int, TaxonomyItem>
     */
    public function taxonomy(): Collection
    {
        return $this->collect($this->fetch('taxonomy'), TaxonomyItem::fromArray(...));
    }

    public function kingdom(): Kingdom
    {
        return Kingdom::fromResponse($this->fetch('kingdom'));
    }

    /**
     * @return Collection<int, Categorization>
     */
    public function categorization(): Collection
    {
        return $this->collect($this->fetch('categorization'), Categorization::fromArray(...));
    }

    /**
     * @return Collection<int, Distribution>
     */
    public function distribution(): Collection
    {
        return $this->collect($this->fetch('distribution'), Distribution::fromArray(...));
    }

    /**
     * @return Collection<int, HostPest>
     */
    public function hosts(): Collection
    {
        return $this->collect($this->fetch('hosts'), HostPest::fromArray(...));
    }

    /**
     * @return Collection<int, HostPest>
     */
    public function pests(): Collection
    {
        return $this->collect($this->fetch('pests'), HostPest::fromArray(...));
    }

    /**
     * Organisms that transmit this one.
     *
     * @return Collection<int, Vector>
     */
    public function vectors(): Collection
    {
        return $this->collect($this->fetch('vectors'), Vector::fromArray(...));
    }

    /**
     * Organisms this one transmits.
     *
     * @return Collection<int, Vector>
     */
    public function vectorOf(): Collection
    {
        return $this->collect($this->fetch('vectorof', 'vectorof'), Vector::fromArray(...));
    }

    /**
     * Biological control agents used against this taxon.
     *
     * @return Collection<int, BiologicalControlAgent>
     */
    public function biologicalControlAgents(): Collection
    {
        return $this->collect($this->fetch('bca'), BiologicalControlAgent::fromArray(...));
    }

    /**
     * Taxa this one is used as a biological control agent against.
     *
     * @return Collection<int, BiologicalControlAgent>
     */
    public function biologicalControlAgentOf(): Collection
    {
        return $this->collect($this->fetch('bcaof'), BiologicalControlAgent::fromArray(...));
    }

    /**
     * @return Collection<int, Photo>
     */
    public function photos(): Collection
    {
        return $this->collect($this->fetch('photos'), Photo::fromArray(...));
    }

    /**
     * @return Collection<int, Document>
     */
    public function documents(): Collection
    {
        return $this->collect($this->fetch('documents'), Document::fromArray(...));
    }

    /**
     * @return Collection<int, Standard>
     */
    public function standards(): Collection
    {
        return $this->collect($this->fetch('standards'), Standard::fromArray(...));
    }

    /**
     * @return Collection<int, ReportingArticle>
     */
    public function reportingArticles(): Collection
    {
        return $this->collect($this->fetch('reporting_articles', 'reporting_articles'), ReportingArticle::fromArray(...));
    }

    /**
     * Does EPPO hold this code at all? Uses the cheap overview endpoint and
     * benefits from negative caching.
     */
    public function exists(): bool
    {
        try {
            $this->overview();

            return true;
        } catch (NotFoundException) {
            return false;
        }
    }

    /**
     * Drop every cached response for this code.
     */
    public function forget(): int
    {
        return $this->cache->forgetSubject($this->code);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function fetch(string $segment, ?string $resourceSuffix = null): array
    {
        return $this->get(
            sprintf('/taxons/taxon/%s/%s', $this->code, $segment),
            'taxon.'.($resourceSuffix ?? $segment),
            $this->code,
        );
    }
}
