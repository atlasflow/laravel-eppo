<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Console;

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Cache\Models\EppoCacheEntry;
use Atlasflow\Eppo\Exceptions\EppoException;
use Atlasflow\Eppo\Http\Endpoint;
use Illuminate\Console\Command;

/**
 * Revalidates stale entries in bulk, off the critical path.
 *
 * Stale-while-revalidate already handles entries somebody reads. This is for
 * the rest — run it on a schedule and the cache never has a cold spot.
 */
final class CacheRefreshCommand extends Command
{
    protected $signature = 'eppo:cache:refresh
        {--resource= : Only refresh this resource, e.g. taxon.distribution or taxon.*}
        {--subject= : Only refresh entries for this code}
        {--limit=500 : Maximum entries to refresh in one run}
        {--all : Refresh every entry, not just stale ones}';

    protected $description = 'Re-fetch stale entries in the durable cache';

    public function handle(CacheManager $cache): int
    {
        $query = EppoCacheEntry::query()->orderBy('stale_at');

        if (! $this->option('all')) {
            $query->whereNotNull('stale_at')->where('stale_at', '<=', now());
        }

        if (is_string($resource = $this->option('resource')) && $resource !== '') {
            str_ends_with($resource, '*')
                ? $query->where('resource', 'like', str_replace('*', '%', $resource))
                : $query->where('resource', $resource);
        }

        if (is_string($subject = $this->option('subject')) && $subject !== '') {
            $query->where('subject', strtoupper($subject));
        }

        $entries = $query->limit((int) $this->option('limit'))->get();

        if ($entries->isEmpty()) {
            $this->components->info('Nothing to refresh.');

            return self::SUCCESS;
        }

        $refreshed = 0;
        $failed = 0;
        $changed = 0;

        $bar = $this->output->createProgressBar($entries->count());
        $bar->start();

        foreach ($entries as $entry) {
            $endpoint = new Endpoint(
                (string) $entry->path,
                (string) $entry->resource,
                $entry->subject === null ? null : (string) $entry->subject,
                is_array($entry->query) ? $entry->query : [],
            );

            $before = (string) $entry->payload_hash;

            try {
                $cache->refresh($endpoint);
                $refreshed++;

                $after = EppoCacheEntry::query()->where('key', $entry->key)->value('payload_hash');

                if ($after !== null && (string) $after !== $before) {
                    $changed++;
                }
            } catch (EppoException) {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->components->info(sprintf(
            'Refreshed %d entries (%d actually changed upstream). %d failed.',
            $refreshed,
            $changed,
            $failed,
        ));

        return self::SUCCESS;
    }
}
