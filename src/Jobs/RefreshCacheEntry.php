<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Jobs;

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Exceptions\EppoException;
use Atlasflow\Eppo\Exceptions\NotFoundException;
use Atlasflow\Eppo\Http\Endpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Revalidates one stale entry out of band. Queued by the cache when a stale
 * copy is served, so the reader never waits for EPPO.
 *
 * Failure is deliberately quiet: the stale copy stays in place and the next
 * read queues another attempt.
 */
final class RefreshCacheEntry implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    /**
     * @var array{path: string, resource: string, subject: ?string, query: array<string, scalar>}
     */
    private array $endpoint;

    public function __construct(Endpoint $endpoint)
    {
        $this->endpoint = $endpoint->jsonSerialize();
    }

    public function handle(CacheManager $cache): void
    {
        $endpoint = Endpoint::fromArray($this->endpoint);

        try {
            $cache->refresh($endpoint);
        } catch (NotFoundException) {
            // The record is gone upstream. Negative caching (if enabled) has
            // already recorded that; nothing else to do.
        } catch (EppoException) {
            // Leave the stale entry alone — it is still better than nothing.
        }
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['eppo', 'eppo:'.$this->endpoint['resource']];
    }

    public function uniqueId(): string
    {
        return $this->endpoint['path'];
    }
}
