<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Cache;

use Atlasflow\Eppo\Http\Endpoint;

/**
 * Cache keys are `sha1(version|path?query)`.
 *
 * The version prefix is what makes a global bust cheap: change
 * `eppo.cache.version` and every lookup misses immediately, while the old rows
 * survive until `eppo:cache:prune` removes them. Reverting the version brings
 * the whole cache straight back.
 */
final class CacheKey
{
    public static function for(Endpoint $endpoint, string $version): string
    {
        return sha1($version.'|'.$endpoint->signature());
    }
}
