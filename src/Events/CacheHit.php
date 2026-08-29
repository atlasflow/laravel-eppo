<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Events;

use Atlasflow\Eppo\Http\Endpoint;

final readonly class CacheHit
{
    /**
     * @param  'l1'|'durable'  $tier
     */
    public function __construct(
        public Endpoint $endpoint,
        public string $tier,
    ) {}
}
