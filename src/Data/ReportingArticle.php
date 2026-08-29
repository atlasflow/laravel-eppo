<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * One article from a Reporting Service issue.
 */
final readonly class ReportingArticle
{
    public function __construct(
        public int $articleId,
        public string $numarticle,
        public string $title,
        public ?int $reportingId,
        public ?string $numrs,
        public ?int $year,
        public ?string $sources,
        public ?string $content,
        public ?\DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $modifiedAt,
        /** @var list<string> EPPO codes the article concerns; only the single-article endpoint populates this. */
        public array $eppocodes = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            articleId: Cast::int($data, 'article_id'),
            numarticle: Cast::string($data, 'numarticle'),
            title: Cast::string($data, 'title'),
            reportingId: Cast::nullableInt($data, 'reporting_id'),
            numrs: Cast::nullableString($data, 'numrs'),
            year: Cast::nullableInt($data, 'repyear'),
            sources: Cast::nullableString($data, 'sources'),
            content: Cast::nullableString($data, 'content'),
            createdAt: Cast::date($data, 'datecreate'),
            modifiedAt: Cast::date($data, 'lastmodif'),
            eppocodes: array_values(array_map(
                static fn (mixed $code): string => (string) $code,
                array_filter(Cast::arr($data, 'eppocodes'), 'is_scalar'),
            )),
        );
    }
}
