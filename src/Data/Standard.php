<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;
use Illuminate\Support\Collection;

/**
 * An EPPO Standard relevant to a taxon.
 */
final readonly class Standard
{
    /**
     * @param  Collection<int, StandardFile>  $files
     */
    public function __construct(
        public int $standardId,
        public string $numstandard,
        public string $title,
        public Collection $files,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            standardId: Cast::int($data, 'standard_id'),
            numstandard: Cast::string($data, 'numstandard'),
            title: Cast::string($data, 'title'),
            files: (new Collection(Cast::arr($data, 'files')))
                ->map(fn (mixed $file): StandardFile => StandardFile::fromArray(is_array($file) ? $file : []))
                ->values(),
        );
    }

    public function urlFor(string $lang): ?string
    {
        return $this->files->firstWhere('lang', $lang)?->url;
    }
}
