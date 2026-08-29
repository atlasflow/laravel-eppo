<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Http;

use Atlasflow\Eppo\Exceptions\ThrottleException;
use Illuminate\Contracts\Cache\Repository;

/**
 * Fixed-window counter shared through the cache, so every worker on a host
 * stays collectively under EPPO's 2000 requests / 10 seconds per IP.
 *
 * Deliberately conservative: it blocks until the window rolls rather than
 * failing the call, because an EPPO 429 costs more than a short sleep.
 */
final class Throttle
{
    public function __construct(
        private readonly Repository $cache,
        private readonly int $maxRequests,
        private readonly int $windowSeconds,
        private readonly int $maxWaitSeconds,
        private readonly string $prefix = 'eppo:throttle',
    ) {}

    /**
     * Block until a slot is free, then consume it.
     */
    public function acquire(): void
    {
        $waited = 0;

        while (true) {
            $window = (int) floor(microtime(true) / $this->windowSeconds);
            $key = $this->prefix.':'.$window;

            $used = (int) $this->cache->get($key, 0);

            if ($used < $this->maxRequests) {
                // add() is atomic on every store that matters; the increment
                // that follows is only reached once the key exists.
                if ($used === 0) {
                    $this->cache->add($key, 0, $this->windowSeconds * 2);
                }

                $this->cache->increment($key);

                return;
            }

            $sleepMs = (int) max(10, ($this->windowSeconds * 1000) - ((int) (microtime(true) * 1000) % ($this->windowSeconds * 1000)));

            if ($waited + (int) ceil($sleepMs / 1000) > $this->maxWaitSeconds) {
                throw ThrottleException::timedOut($this->maxWaitSeconds);
            }

            usleep($sleepMs * 1000);
            $waited += (int) ceil($sleepMs / 1000);
        }
    }
}
