<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * A document attached to a taxon.
 */
final readonly class Document
{
    public function __construct(
        public int $documentId,
        public string $title,
        public ?string $description,
        public ?string $category,
        public string $url,
        public ?\DateTimeImmutable $publishedAt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            documentId: Cast::int($data, 'document_id'),
            title: Cast::string($data, 'title'),
            description: Cast::nullableString($data, 'description'),
            category: Cast::nullableString($data, 'category'),
            url: Cast::string($data, 'url'),
            publishedAt: Cast::date($data, 'pubdate'),
        );
    }
}
