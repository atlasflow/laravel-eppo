<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * One row of /taxons/list — the change feed `eppo:sync` walks.
 */
final readonly class TaxonListItem
{
    public function __construct(
        public string $eppocode,
        public bool $isActive,
        public ?string $replacedBy,
        public ?string $datatype,
        public ?\DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eppocode: Cast::string($data, 'eppocode'),
            isActive: Cast::bool($data, 'is_active', true),
            replacedBy: Cast::nullableString($data, 'replacedby'),
            datatype: Cast::nullableString($data, 'datatype'),
            createdAt: Cast::date($data, 'datecreate'),
            updatedAt: Cast::date($data, 'dateupdate'),
        );
    }
}
