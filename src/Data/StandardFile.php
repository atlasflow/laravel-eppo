<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * One language edition of an EPPO Standard.
 */
final readonly class StandardFile
{
    public function __construct(
        public string $filename,
        public string $lang,
        public string $url,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            filename: Cast::string($data, 'filename'),
            lang: Cast::string($data, 'lang'),
            url: Cast::string($data, 'url'),
        );
    }
}
