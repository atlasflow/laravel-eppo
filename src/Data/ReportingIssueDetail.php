<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;
use Illuminate\Support\Collection;

/**
 * A Reporting Service issue together with its article headlines.
 */
final readonly class ReportingIssueDetail
{
    /**
     * @param  Collection<int, ReportingArticle>  $articles
     */
    public function __construct(
        public ReportingIssue $issue,
        public Collection $articles,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            issue: ReportingIssue::fromArray($data),
            articles: (new Collection(Cast::arr($data, 'articles')))
                ->map(fn (mixed $article): ReportingArticle => ReportingArticle::fromArray(is_array($article) ? $article : []))
                ->values(),
        );
    }
}
