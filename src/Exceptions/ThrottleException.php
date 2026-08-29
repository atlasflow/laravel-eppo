<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Exceptions;

/**
 * The local throttle refused to release a slot within `throttle.max_wait_seconds`.
 * Nothing was sent to EPPO.
 */
class ThrottleException extends EppoException
{
    public static function timedOut(int $waited): self
    {
        return new self(sprintf(
            'Local EPPO throttle did not free a slot within %d seconds. '
            .'Raise eppo.throttle.max_wait_seconds or lower your request rate.',
            $waited
        ));
    }
}
