<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Events;

use Atlasflow\Eppo\Http\Endpoint;

final readonly class RequestSucceeded
{
    public function __construct(
        public Endpoint $endpoint,
        public int $status,
    ) {}
}
