<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * One monthly issue of the EPPO Reporting Service.
 */
final readonly class ReportingIssue
{
    public function __construct(
        public int $reportingId,
        public string $numrs,
        public int $year,
        public string $reference,
        public ?\DateTimeImmutable $createdAt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            reportingId: Cast::int($data, 'reporting_id'),
            numrs: Cast::string($data, 'numrs'),
            year: Cast::int($data, 'repyear'),
            reference: Cast::string($data, 'reference'),
            createdAt: Cast::date($data, 'datecreate'),
        );
    }
}
