<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Events;

use Atlasflow\Eppo\Http\Endpoint;

final readonly class RequestFailed
{
    public function __construct(
        public Endpoint $endpoint,
        public int $status,
        public string $reason,
    ) {}
}
