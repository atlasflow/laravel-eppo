<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * One name for a taxon: scientific, common, or a translation.
 */
final readonly class TaxonName
{
    public function __construct(
        public int $nameId,
        public string $fullname,
        public string $langIso,
        public ?string $countryIso,
        public bool $preferred,
        public ?string $author,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            nameId: Cast::int($data, 'name_id'),
            fullname: Cast::string($data, 'fullname'),
            langIso: Cast::string($data, 'lang_iso'),
            countryIso: Cast::nullableString($data, 'country_iso'),
            preferred: Cast::bool($data, 'preferred'),
            author: Cast::nullableString($data, 'author'),
        );
    }
}
