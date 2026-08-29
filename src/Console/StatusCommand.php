<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Console;

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Eppo;
use Atlasflow\Eppo\Exceptions\EppoException;
use Illuminate\Console\Command;

final class StatusCommand extends Command
{
    protected $signature = 'eppo:status';

    protected $description = 'Show EPPO API health and durable cache statistics';

    public function handle(Eppo $eppo, CacheManager $cache): int
    {
        try {
            $status = $eppo->fresh()->status();
            $this->components->info(sprintf('EPPO API %s — %s', $status->version, $status->status));
        } catch (EppoException $e) {
            $this->components->error('EPPO API unreachable: '.$e->getMessage());
        }

        $stats = $cache->stats();

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Cache version</>', $cache->version());
        $this->components->twoColumnDetail('<fg=gray>Entries</>', number_format($stats['entries']));
        $this->components->twoColumnDetail('<fg=gray>Subjects</>', number_format($stats['subjects']));
        $this->components->twoColumnDetail('<fg=gray>Stale</>', number_format($stats['stale']));
        $this->components->twoColumnDetail('<fg=gray>Payload size</>', $this->humanBytes($stats['bytes']));
        $this->components->twoColumnDetail('<fg=gray>Oldest fetch</>', $stats['oldest'] ?? '—');
        $this->components->twoColumnDetail('<fg=gray>Newest fetch</>', $stats['newest'] ?? '—');

        return self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes = (int) ($bytes / 1024);
            $i++;
        }

        return $bytes.' '.$units[$i];
    }
}
