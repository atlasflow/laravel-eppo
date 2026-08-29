<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * One rendition of a photo.
 */
final readonly class PhotoFile
{
    public function __construct(
        public string $size,
        public string $url,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            size: Cast::string($data, 'size'),
            url: Cast::string($data, 'url'),
        );
    }
}
