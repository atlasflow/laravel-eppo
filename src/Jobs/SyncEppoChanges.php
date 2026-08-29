<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Jobs;

use Atlasflow\Eppo\Sync\ChangeSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queueable form of `eppo:sync`, for scheduling without a shell.
 *
 *     Schedule::job(new SyncEppoChanges)->dailyAt('03:00');
 */
final class SyncEppoChanges implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public readonly ?string $since = null,
        public readonly ?bool $refresh = null,
    ) {}

    public function handle(ChangeSync $sync): void
    {
        $sync->run(since: $this->since, refresh: $this->refresh);
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['eppo', 'eppo:sync'];
    }
}
