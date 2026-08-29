<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Sync;

use DateTimeImmutable;

final readonly class SyncResult
{
    /**
     * @param  list<string>  $codes
     */
    public function __construct(
        public string $since,
        public int $scanned,
        public int $invalidatedEntries,
        public int $refreshed,
        public DateTimeImmutable $ranAt,
        public array $codes = [],
    ) {}
}
