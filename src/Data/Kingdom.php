<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * The kingdom a taxon belongs to.
 */
final readonly class Kingdom
{
    public function __construct(
        public string $eppocode,
        public string $prefname,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eppocode: Cast::string($data, 'eppocode'),
            prefname: Cast::string($data, 'prefname'),
        );
    }

    /**
     * The API nests this one under an `eppocode` key.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromResponse(array $data): self
    {
        $inner = Cast::arr($data, 'eppocode');

        return self::fromArray($inner === [] ? $data : $inner);
    }
}
