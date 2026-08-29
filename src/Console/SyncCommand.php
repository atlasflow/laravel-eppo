<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Console;

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Console\Concerns\RequiresCache;
use Atlasflow\Eppo\Data\TaxonListItem;
use Atlasflow\Eppo\Eppo;
use Atlasflow\Eppo\Sync\ChangeSync;
use Illuminate\Console\Command;

/**
 * The scheduled job that keeps a years-old cache honest.
 */
final class SyncCommand extends Command
{
    use RequiresCache;

    protected $signature = 'eppo:sync
        {--since= : Look for changes from this date onwards (default: where the last run finished)}
        {--refresh : Re-fetch each invalidated resource instead of leaving a hole}
        {--page-size= : Codes to request per page (max 1000)}
        {--dry-run : Report what would be invalidated without touching the cache}';

    protected $description = 'Ask EPPO which codes changed and invalidate only those cache entries';

    public function handle(ChangeSync $sync, CacheManager $cache): int
    {
        if ($this->cacheIsOff($cache)) {
            return self::FAILURE;
        }

        $since = $this->option('since');
        $from = $sync->resolveSince(is_string($since) ? $since : null);

        $this->components->info(sprintf('Reading EPPO changes since %s', $from->format('Y-m-d')));

        if ($this->option('dry-run')) {
            return $this->dryRun($sync, $from->format('Y-m-d'));
        }

        $result = $sync->run(
            since: $from,
            pageSize: $this->option('page-size') === null ? null : (int) $this->option('page-size'),
            refresh: $this->option('refresh') ? true : null,
            onChange: function (TaxonListItem $taxon, int $removed): void {
                $this->components->twoColumnDetail(
                    $taxon->eppocode.($taxon->replacedBy !== null ? ' <fg=yellow>→ '.$taxon->replacedBy.'</>' : ''),
                    $removed === 0 ? '<fg=gray>not cached</>' : $removed.' entries busted',
                );
            },
        );

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Codes changed</>', (string) $result->scanned);
        $this->components->twoColumnDetail('<fg=gray>Entries invalidated</>', (string) $result->invalidatedEntries);

        if ($result->refreshed > 0) {
            $this->components->twoColumnDetail('<fg=gray>Entries re-fetched</>', (string) $result->refreshed);
        }

        $this->components->info('Sync complete.');

        return self::SUCCESS;
    }

    private function dryRun(ChangeSync $sync, string $from): int
    {
        $count = 0;

        foreach (app(Eppo::class)->fresh()->taxons()->changedSince($from) as $taxon) {
            $this->line('  '.$taxon->eppocode.' <fg=gray>'.($taxon->updatedAt?->format('Y-m-d') ?? '').'</>');
            $count++;
        }

        $this->newLine();
        $this->components->info(sprintf('%d code(s) changed since %s. Nothing was invalidated.', $count, $from));

        return self::SUCCESS;
    }
}
