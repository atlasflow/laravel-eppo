<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Exceptions;

use Throwable;

/**
 * A request reached EPPO and came back with a status we cannot use.
 */
class RequestException extends EppoException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $url,
        public readonly array $body = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromResponse(int $status, string $url, array $body): self
    {
        $message = self::messageFrom($body) ?? sprintf('EPPO request failed with status %d.', $status);

        return match (true) {
            $status === 400 => new BadRequestException($message, $status, $url, $body),
            $status === 401 => new AuthenticationException($message, $status, $url, $body),
            $status === 403 => new AuthorizationException($message, $status, $url, $body),
            $status === 404 => new NotFoundException($message, $status, $url, $body),
            $status === 429 => RateLimitException::fromBody($message, $url, $body),
            $status >= 500 => new ServerException($message, $status, $url, $body),
            default => new self($message, $status, $url, $body),
        };
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function messageFrom(array $body): ?string
    {
        foreach (['message', 'error'] as $field) {
            if (isset($body[$field]) && is_string($body[$field]) && $body[$field] !== '') {
                return $body[$field];
            }
        }

        return null;
    }
}
