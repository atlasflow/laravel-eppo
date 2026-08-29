<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Resources;

use Atlasflow\Eppo\Data\ReportingArticle;
use Atlasflow\Eppo\Data\ReportingIssue;
use Atlasflow\Eppo\Data\ReportingIssueDetail;
use Generator;
use Illuminate\Support\Collection;

/**
 * The EPPO Reporting Service: a monthly bulletin of phytosanitary events.
 */
final class ReportingsResource extends Resource
{
    /**
     * EPPO clamps the page size here; asking for more than this returns two
     * rows rather than an error. Verified against the live API, 2026-08-29.
     */
    public const MAX_LIMIT = 1000;

    /**
     * One page of issues, newest EPPO ordering first.
     *
     * `limit` and `offset` are undocumented but supported — the endpoint
     * returns only the first 100 issues without them, and there are over 500.
     *
     * @return Collection<int, ReportingIssue>
     */
    public function list(int $limit = 100, int $offset = 0): Collection
    {
        return $this->collect(
            $this->get('/reportings/list', 'reportings.list', null, [
                'limit' => max(1, min($limit, self::MAX_LIMIT)),
                'offset' => max(0, $offset),
            ]),
            ReportingIssue::fromArray(...),
        );
    }

    /**
     * Every issue, page by page.
     *
     * @return Generator<int, ReportingIssue>
     */
    public function cursor(int $pageSize = 500): Generator
    {
        $offset = 0;

        do {
            $page = $this->list(limit: $pageSize, offset: $offset);

            foreach ($page as $issue) {
                yield $issue;
            }

            $offset += $page->count();
        } while ($page->count() === min($pageSize, self::MAX_LIMIT));
    }

    public function issue(int $reportingId): ReportingIssueDetail
    {
        return ReportingIssueDetail::fromArray($this->get(
            sprintf('/reportings/reporting/%d', $reportingId),
            'reportings.issue',
            (string) $reportingId,
        ));
    }

    public function article(int $articleId): ReportingArticle
    {
        return ReportingArticle::fromArray($this->get(
            sprintf('/reportings/article/%d', $articleId),
            'reportings.article',
            (string) $articleId,
        ));
    }
}
