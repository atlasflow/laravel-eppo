<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Exceptions;

use Throwable;

/**
 * EPPO could not be reached at all — DNS, TLS, timeout, or every configured
 * server exhausted.
 */
class ConnectionException extends EppoException
{
    public function __construct(string $message, public readonly string $url = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
