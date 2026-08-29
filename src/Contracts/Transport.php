<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Contracts;

use Atlasflow\Eppo\Exceptions\EppoException;
use Atlasflow\Eppo\Http\Endpoint;

interface Transport
{
    /**
     * Perform the call and return the decoded JSON body.
     *
     * @return array<array-key, mixed>
     *
     * @throws EppoException
     */
    public function get(Endpoint $endpoint): array;
}
