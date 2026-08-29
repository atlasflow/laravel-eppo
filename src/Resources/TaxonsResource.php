<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Resources;

use Atlasflow\Eppo\Data\TaxonListItem;
use Atlasflow\Eppo\Data\TaxonPage;
use DateTimeInterface;
use Generator;

/**
 * The taxon index — and, through `changedSince()`, the change feed that makes
 * targeted cache invalidation possible.
 */
final class TaxonsResource extends Resource
{
    /**
     * One page of the index.
     *
     * @param  'creation'|'datecreate'|'eppocode'|'dateupdate'  $orderBy
     */
    public function list(
        int $limit = 100,
        int $offset = 0,
        ?string $createdFrom = null,
        ?string $updatedFrom = null,
        string $orderBy = 'creation',
        bool $ascending = true,
        bool $ephemeral = false,
    ): TaxonPage {
        return TaxonPage::fromArray($this->get('/taxons/list', 'taxons.list', null, [
            'limit' => max(1, min($limit, 1000)),
            'offset' => max(0, $offset),
            'createdFromDate' => $createdFrom,
            'updatedFromDate' => $updatedFrom,
            'orderBy' => $orderBy,
            'orderAsc' => $ascending,
        ], $ephemeral));
    }

    /**
     * Walk the whole index page by page, yielding one taxon at a time.
     *
     * @return Generator<int, TaxonListItem>
     */
    public function cursor(
        int $pageSize = 1000,
        ?string $createdFrom = null,
        ?string $updatedFrom = null,
        bool $ephemeral = false,
    ): Generator {
        $offset = 0;

        do {
            $page = $this->list(
                limit: $pageSize,
                offset: $offset,
                createdFrom: $createdFrom,
                updatedFrom: $updatedFrom,
                orderBy: 'eppocode',
                ephemeral: $ephemeral,
            );

            foreach ($page->items as $item) {
                yield $item;
            }

            $offset = $page->nextOffset();
        } while ($page->hasMore());
    }

    /**
     * Codes EPPO created or updated on or after `$since`. This is the feed
     * `eppo:sync` reads; it is the only cheap way to know what to invalidate.
     *
     * @return Generator<int, TaxonListItem>
     */
    public function changedSince(DateTimeInterface|string $since, int $pageSize = 1000): Generator
    {
        $date = $since instanceof DateTimeInterface
            ? $since->format('Y-m-d')
            : (new \DateTimeImmutable($since))->format('Y-m-d');

        // Never cached: the point of this feed is to correct the cache.
        return $this->cursor(
            pageSize: $pageSize,
            createdFrom: $date,
            updatedFrom: $date,
            ephemeral: true,
        );
    }
}
