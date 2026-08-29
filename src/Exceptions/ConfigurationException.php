<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Exceptions;

class ConfigurationException extends EppoException
{
    public static function missingApiKey(): self
    {
        return new self(
            'No EPPO API key configured. Set EPPO_API_KEY in your environment; '
            .'generate a token from the dashboard at https://data.eppo.int.'
        );
    }
}
