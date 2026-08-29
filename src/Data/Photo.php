<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;
use Illuminate\Support\Collection;

/**
 * A photo of a taxon, with every rendition EPPO publishes.
 *
 * Renditions are named by pixel dimensions, not by size words — EPPO currently
 * serves `1024x0` (full width) and `220x130` (thumbnail). Prefer `largest()`
 * and `thumbnail()` over hard-coding either.
 */
final readonly class Photo
{
    /**
     * @param  list<string>  $tags
     * @param  Collection<int, PhotoFile>  $files
     */
    public function __construct(
        public int $photoId,
        public ?string $description,
        public ?string $authors,
        public array $tags,
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
            tags: array_values(array_map(
                static fn (mixed $tag): string => (string) $tag,
                array_filter(Cast::arr($data, 'tags'), 'is_scalar'),
            )),
            modifiedAt: Cast::date($data, 'lastmod'),
            files: (new Collection(Cast::arr($data, 'files')))
                ->map(fn (mixed $file): PhotoFile => PhotoFile::fromArray(is_array($file) ? $file : []))
                ->values(),
        );
    }

    /**
     * The widest rendition available.
     */
    public function largest(): ?PhotoFile
    {
        return $this->files->sortByDesc(fn (PhotoFile $file): int => $file->width())->first();
    }

    /**
     * The narrowest rendition available.
     */
    public function thumbnail(): ?PhotoFile
    {
        return $this->files->sortBy(fn (PhotoFile $file): int => $file->width())->first();
    }

    /**
     * URL of a specific rendition ("1024x0"), or of the widest one when no
     * size is given. Falls back to the widest if the size does not exist.
     */
    public function url(?string $size = null): ?string
    {
        if ($size !== null && ($match = $this->files->firstWhere('size', $size)) !== null) {
            return $match->url;
        }

        return $this->largest()?->url;
    }
}
