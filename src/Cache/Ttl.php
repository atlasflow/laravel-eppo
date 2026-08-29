<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Cache;

/**
 * Readability helper for the `eppo.cache.ttl` config block. Every method
 * returns plain seconds — there is nothing else to it.
 */
final class Ttl
{
    public const FOREVER = null;

    public static function seconds(int $n): int
    {
        return $n;
    }

    public static function minutes(int $n): int
    {
        return $n * 60;
    }

    public static function hours(int $n): int
    {
        return $n * 3600;
    }

    public static function days(int $n): int
    {
        return $n * 86400;
    }

    public static function weeks(int $n): int
    {
        return $n * 604800;
    }

    public static function years(int $n): int
    {
        return $n * 31536000;
    }
}
