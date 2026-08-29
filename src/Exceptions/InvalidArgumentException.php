<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Exceptions;

class InvalidArgumentException extends EppoException
{
    public static function eppoCode(string $value): self
    {
        return new self(sprintf(
            'Invalid EPPO code [%s]. Codes are 5 or 6 characters of A-Z and 0-9.',
            $value
        ));
    }

    public static function isoCountry(string $value): self
    {
        return new self(sprintf(
            'Invalid ISO country code [%s]. Codes are 2 characters of A-Z and 0-9; '
            .'see the /references/countries endpoint for the accepted values.',
            $value
        ));
    }

    public static function rppoCode(string $value): self
    {
        return new self(sprintf(
            'Invalid RPPO code [%s]. Codes are the digit 9 followed by one letter, e.g. 9A.',
            $value
        ));
    }
}
