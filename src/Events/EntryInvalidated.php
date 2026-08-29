<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Events;

/**
 * Something removed cache entries: a manual bust, `eppo:sync`, or a prune.
 */
final readonly class EntryInvalidated
{
    public function __construct(
        public string $scope,
        public string $target,
        public int $count,
    ) {}
}
