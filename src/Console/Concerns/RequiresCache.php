<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Console\Concerns;

use Atlasflow\Eppo\Cache\CacheManager;

/**
 * Caching is opt-in, so the commands that only make sense with it on say so
 * plainly instead of failing on a table that was never created.
 */
trait RequiresCache
{
    protected function cacheIsOff(CacheManager $cache): bool
    {
        if ($cache->enabled()) {
            return false;
        }

        $this->components->warn(
            'EPPO caching is off. Set EPPO_CACHE=true, publish the migrations '
            .'(php artisan vendor:publish --tag=eppo-migrations) and run php artisan migrate.'
        );

        return true;
    }
}
