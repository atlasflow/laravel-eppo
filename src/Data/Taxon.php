<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * A taxon overview: the preferred name and the record's lifecycle.
 *
 * `replacedBy` is set when the code has been deprecated in favour of another —
 * always follow it before treating a lookup as authoritative.
 */
final readonly class Taxon
{
    public function __construct(
        public string $eppocode,
        public string $prefname,
        public bool $isActive,
        public ?string $replacedBy,
        public ?string $datatype,
        public ?string $infos,
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
            prefname: Cast::string($data, 'prefname'),
            isActive: Cast::bool($data, 'is_active', true),
            replacedBy: Cast::nullableString($data, 'replacedby'),
            datatype: Cast::nullableString($data, 'datatype'),
            infos: Cast::nullableString($data, 'infos'),
            createdAt: Cast::date($data, 'datecreate'),
            updatedAt: Cast::date($data, 'lastupdate'),
        );
    }

    /**
     * True when this code is current: active and not superseded.
     */
    public function isUsable(): bool
    {
        return $this->isActive && $this->replacedBy === null;
    }
}
