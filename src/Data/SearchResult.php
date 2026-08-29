<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * One hit from /tools/search.
 */
final readonly class SearchResult
{
    public function __construct(
        public string $eppocode,
        public string $fullName,
        public bool $isPreferred,
        public string $preferredName,
        public ?string $isolang,
        public ?string $language,
        public ?string $statuscode,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eppocode: Cast::string($data, 'eppocode'),
            fullName: Cast::string($data, 'full_name'),
            isPreferred: Cast::bool($data, 'is_preferred'),
            preferredName: Cast::string($data, 'preferred_name'),
            isolang: Cast::nullableString($data, 'isolang'),
            language: Cast::nullableString($data, 'language'),
            statuscode: Cast::nullableString($data, 'statuscode'),
        );
    }
}
