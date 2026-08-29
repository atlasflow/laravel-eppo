<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Support;

use Atlasflow\Eppo\Exceptions\InvalidArgumentException;

/**
 * Validation for the three identifier shapes the EPPO API accepts. Catching a
 * malformed code here saves a round trip and a confusing 400.
 */
final class Code
{
    public const EPPO_PATTERN = '/^[0-9A-Z]{5,6}$/';

    public const ISO_COUNTRY_PATTERN = '/^[0-9A-Z]{2}$/';

    public const RPPO_PATTERN = '/^9[A-Z]$/';

    public static function eppo(string $code): string
    {
        $code = strtoupper(trim($code));

        if (preg_match(self::EPPO_PATTERN, $code) !== 1) {
            throw InvalidArgumentException::eppoCode($code);
        }

        return $code;
    }

    public static function isEppo(string $code): bool
    {
        return preg_match(self::EPPO_PATTERN, strtoupper(trim($code))) === 1;
    }

    public static function country(string $code): string
    {
        $code = strtoupper(trim($code));

        if (preg_match(self::ISO_COUNTRY_PATTERN, $code) !== 1) {
            throw InvalidArgumentException::isoCountry($code);
        }

        return $code;
    }

    public static function rppo(string $code): string
    {
        $code = strtoupper(trim($code));

        if (preg_match(self::RPPO_PATTERN, $code) !== 1) {
            throw InvalidArgumentException::rppoCode($code);
        }

        return $code;
    }
}
