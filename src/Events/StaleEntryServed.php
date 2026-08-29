<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Events;

use Atlasflow\Eppo\Http\Endpoint;

/**
 * A durable entry past its stale time was returned. `revalidating` says whether
 * a refresh was queued; `becauseOfError` is true when EPPO was unreachable and
 * the stale copy was served as a fallback.
 */
final readonly class StaleEntryServed
{
    public function __construct(
        public Endpoint $endpoint,
        public bool $revalidating,
        public bool $becauseOfError = false,
    ) {}
}
