<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * How many records of each kind exist for a taxon. Cheap way to skip
 * requests that would come back empty.
 */
final readonly class TaxonInfos
{
    public function __construct(
        public int $datasheet,
        public int $categorization,
        public int $distribution,
        public int $pests,
        public int $hosts,
        public int $pathwayPest,
        public int $pathwayHost,
        public int $photos,
        public int $expertise,
        public int $reporting,
        public int $documents,
        public int $specimens,
        public int $vectorsPests,
        public int $vectorsHosts,
        public int $eppoLinks,
        public int $bca,
        public int $bcao,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            datasheet: Cast::int($data, 'datasheet'),
            categorization: Cast::int($data, 'categorization'),
            distribution: Cast::int($data, 'distribution'),
            pests: Cast::int($data, 'pests'),
            hosts: Cast::int($data, 'hosts'),
            pathwayPest: Cast::int($data, 'pathwaypest'),
            pathwayHost: Cast::int($data, 'pathwayhost'),
            photos: Cast::int($data, 'photos'),
            expertise: Cast::int($data, 'expertise'),
            reporting: Cast::int($data, 'reporting'),
            documents: Cast::int($data, 'documents'),
            specimens: Cast::int($data, 'specimens'),
            vectorsPests: Cast::int($data, 'vectorspests'),
            vectorsHosts: Cast::int($data, 'vectorshosts'),
            eppoLinks: Cast::int($data, 'eppolinks'),
            bca: Cast::int($data, 'bca'),
            bcao: Cast::int($data, 'bcao'),
        );
    }
}
