<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Exceptions;

class RateLimitException extends RequestException
{
    public ?int $retryAfter = null;

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromBody(string $message, string $url, array $body): self
    {
        $exception = new self($message, 429, $url, $body);

        $limit = $body['rate_limit'] ?? [];

        if (is_array($limit) && isset($limit['retry_after']) && is_numeric($limit['retry_after'])) {
            $exception->retryAfter = (int) $limit['retry_after'];
        }

        return $exception;
    }
}
