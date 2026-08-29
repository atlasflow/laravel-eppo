<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Resources;

use Atlasflow\Eppo\Data\ReportingArticle;
use Atlasflow\Eppo\Data\ReportingIssue;
use Atlasflow\Eppo\Data\ReportingIssueDetail;
use Illuminate\Support\Collection;

/**
 * The EPPO Reporting Service: a monthly bulletin of phytosanitary events.
 */
final class ReportingsResource extends Resource
{
    /**
     * @return Collection<int, ReportingIssue>
     */
    public function list(): Collection
    {
        return $this->collect(
            $this->get('/reportings/list', 'reportings.list'),
            ReportingIssue::fromArray(...),
        );
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
