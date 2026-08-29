<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;
use Illuminate\Support\Collection;

/**
 * One page of /taxons/list, with enough metadata to walk the rest.
 */
final readonly class TaxonPage
{
    /**
     * @param  Collection<int, TaxonListItem>  $items
     */
    public function __construct(
        public Collection $items,
        public int $offset,
        public int $limit,
        public int $total,
        public int $count,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $pagination = Cast::arr($data, 'pagination');

        return new self(
            items: (new Collection(Cast::arr($data, 'data')))
                ->map(fn (mixed $row): TaxonListItem => TaxonListItem::fromArray(is_array($row) ? $row : []))
                ->values(),
            offset: Cast::int($pagination, 'offset'),
            limit: Cast::int($pagination, 'limit', 100),
            total: Cast::int($pagination, 'total'),
            count: Cast::int($pagination, 'count'),
        );
    }

    public function hasMore(): bool
    {
        return $this->count > 0 && ($this->offset + $this->count) < $this->total;
    }

    public function nextOffset(): int
    {
        return $this->offset + max($this->count, 1);
    }
}
