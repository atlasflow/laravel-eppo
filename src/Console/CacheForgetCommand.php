<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Console;

use Atlasflow\Eppo\Cache\CacheManager;
use Illuminate\Console\Command;

final class CacheForgetCommand extends Command
{
    protected $signature = 'eppo:cache:forget
        {target? : An EPPO code, ISO country code or RPPO code to bust}
        {--resource= : Bust a resource instead, e.g. taxon.distribution or references.*}
        {--all : Bust everything, including entries from older cache versions}';

    protected $description = 'Invalidate EPPO cache entries by subject, by resource, or entirely';

    public function handle(CacheManager $cache): int
    {
        if ($this->option('all')) {
            if (! $this->confirmToProceed()) {
                return self::FAILURE;
            }

            $count = $cache->flush();
            $this->components->info(sprintf('Removed %d cache entries.', $count));

            return self::SUCCESS;
        }

        $resource = $this->option('resource');

        if (is_string($resource) && $resource !== '') {
            $count = $cache->forgetResource($resource);
            $this->components->info(sprintf('Removed %d entries for resource [%s].', $count, $resource));

            return self::SUCCESS;
        }

        $target = $this->argument('target');

        if (! is_string($target) || $target === '') {
            $this->components->error('Give a subject to bust, --resource=, or --all.');

            return self::INVALID;
        }

        $subject = strtoupper($target);
        $count = $cache->forgetSubject($subject);

        $this->components->info(sprintf('Removed %d entries for [%s].', $count, $subject));

        return self::SUCCESS;
    }

    private function confirmToProceed(): bool
    {
        if ($this->option('no-interaction') || ! $this->getLaravel()->environment('production')) {
            return true;
        }

        return $this->components->confirm('Discard the entire durable EPPO cache?', false);
    }
}
