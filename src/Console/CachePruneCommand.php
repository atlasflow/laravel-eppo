<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Console;

use Atlasflow\Eppo\Cache\CacheManager;
use Illuminate\Console\Command;

final class CachePruneCommand extends Command
{
    protected $signature = 'eppo:cache:prune';

    protected $description = 'Delete hard-expired entries and entries left behind by an older cache version';

    public function handle(CacheManager $cache): int
    {
        $count = $cache->prune();

        $this->components->info(sprintf(
            'Pruned %d entries. Current cache version is [%s].',
            $count,
            $cache->version(),
        ));

        return self::SUCCESS;
    }
}
