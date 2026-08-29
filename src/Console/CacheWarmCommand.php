<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Console;

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Console\Concerns\RequiresCache;
use Atlasflow\Eppo\Eppo;
use Atlasflow\Eppo\Exceptions\EppoException;
use Atlasflow\Eppo\Http\Endpoint;
use Atlasflow\Eppo\Support\Code;
use Illuminate\Console\Command;

/**
 * Front-loads the durable cache so the first real user never waits.
 *
 * Give it codes on the command line, a newline-separated file, or nothing at
 * all to top up whatever is already cached.
 */
final class CacheWarmCommand extends Command
{
    use RequiresCache;

    protected $signature = 'eppo:cache:warm
        {codes?* : EPPO codes to warm (default: every code already in the cache)}
        {--file= : Read codes from a file, one per line}
        {--with= : Comma-separated resources, e.g. overview,names,distribution (default: eppo.warm.resources)}
        {--references : Warm the reference tables (countries, lists, classifications) too}
        {--force : Re-fetch even entries that are still fresh}';

    protected $description = 'Pre-fetch EPPO records into the durable cache';

    public function handle(Eppo $eppo, CacheManager $cache): int
    {
        if ($this->cacheIsOff($cache)) {
            return self::FAILURE;
        }

        if ($this->option('references')) {
            $this->warmReferences($eppo);
        }

        $codes = $this->codes($cache);

        if ($codes === []) {
            $this->components->warn('No codes to warm. Pass codes, --file=, or --references.');

            return self::SUCCESS;
        }

        $resources = $this->resources();
        $force = (bool) $this->option('force');

        $bar = $this->output->createProgressBar(count($codes) * count($resources));
        $bar->start();

        $fetched = 0;
        $failed = 0;

        foreach ($codes as $code) {
            foreach ($resources as $resource) {
                $endpoint = Endpoint::make(
                    sprintf('/taxons/taxon/%s/%s', $code, $resource),
                    'taxon.'.$resource,
                    $code,
                );

                try {
                    $force ? $cache->refresh($endpoint) : $cache->get($endpoint);
                    $fetched++;
                } catch (EppoException) {
                    $failed++;
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->components->info(sprintf('Warmed %d entries across %d codes. %d failed.', $fetched, count($codes), $failed));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function codes(CacheManager $cache): array
    {
        /** @var list<string> $argument */
        $argument = (array) $this->argument('codes');

        $file = $this->option('file');

        if (is_string($file) && $file !== '') {
            if (! is_readable($file)) {
                $this->components->error(sprintf('Cannot read [%s].', $file));

                return [];
            }

            $argument = array_merge($argument, preg_split('/\R/', (string) file_get_contents($file)) ?: []);
        }

        if ($argument === []) {
            foreach ($cache->durable()->subjects() as $subject) {
                if (Code::isEppo($subject)) {
                    $argument[] = $subject;
                }
            }
        }

        $codes = [];

        foreach ($argument as $code) {
            $code = strtoupper(trim($code));

            if ($code !== '' && Code::isEppo($code)) {
                $codes[$code] = $code;
            }
        }

        return array_values($codes);
    }

    /**
     * @return list<string>
     */
    private function resources(): array
    {
        $with = $this->option('with');

        if (is_string($with) && $with !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $with))));
        }

        /** @var list<string> $default */
        $default = config('eppo.warm.resources', ['overview']);

        return $default;
    }

    private function warmReferences(Eppo $eppo): void
    {
        $references = $eppo->references();

        foreach (['countries', 'countriesStates', 'rppos', 'qLists', 'distributionStatuses', 'pestHostClassifications', 'vectorClassifications'] as $method) {
            try {
                $references->{$method}();
                $this->components->twoColumnDetail('references.'.$method, '<fg=green>cached</>');
            } catch (EppoException $e) {
                $this->components->twoColumnDetail('references.'.$method, '<fg=red>'.$e->getMessage().'</>');
            }
        }
    }
}
