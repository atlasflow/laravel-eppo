<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;
use Illuminate\Support\Collection;

/**
 * A photo of a taxon, with every rendition EPPO publishes.
 */
final readonly class Photo
{
    /**
     * @param  Collection<int, PhotoFile>  $files
     */
    public function __construct(
        public int $photoId,
        public ?string $description,
        public ?string $authors,
        public ?string $tags,
        public ?\DateTimeImmutable $modifiedAt,
        public Collection $files,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            photoId: Cast::int($data, 'photo_id'),
            description: Cast::nullableString($data, 'descinfo'),
            authors: Cast::nullableString($data, 'authors'),
            tags: Cast::nullableString($data, 'tags'),
            modifiedAt: Cast::date($data, 'lastmod'),
            files: (new Collection(Cast::arr($data, 'files')))
                ->map(fn (mixed $file): PhotoFile => PhotoFile::fromArray(is_array($file) ? $file : []))
                ->values(),
        );
    }

    public function url(string $size = 'large'): ?string
    {
        return $this->files->firstWhere('size', $size)->url
            ?? $this->files->first()?->url;
    }
}
