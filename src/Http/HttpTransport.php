<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Http;

use Atlasflow\Eppo\Contracts\Transport;
use Atlasflow\Eppo\Events\RequestFailed;
use Atlasflow\Eppo\Events\RequestSucceeded;
use Atlasflow\Eppo\Exceptions\ConfigurationException;
use Atlasflow\Eppo\Exceptions\ConnectionException;
use Atlasflow\Eppo\Exceptions\RateLimitException;
use Atlasflow\Eppo\Exceptions\RequestException;
use Atlasflow\Eppo\Exceptions\ServerException;
use Atlasflow\Eppo\Support\Emit;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\ConnectionException as LaravelConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

/**
 * The only place in the package that talks to EPPO.
 *
 * Responsibilities, in order: throttle, send, interpret status, retry what is
 * worth retrying, fail over to a secondary server when the primary is down.
 */
final class HttpTransport implements Transport
{
    /**
     * @param  array{times: int, base_delay_ms: int, max_delay_ms: int, jitter: bool}  $retry
     * @param  list<string>  $fallbackUrls
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly array $fallbackUrls = [],
        private readonly int $timeout = 15,
        private readonly int $connectTimeout = 5,
        private readonly string $userAgent = 'atlasflow/laravel-eppo',
        private readonly array $retry = ['times' => 3, 'base_delay_ms' => 250, 'max_delay_ms' => 10000, 'jitter' => true],
        private readonly ?Throttle $throttle = null,
        private readonly ?Dispatcher $events = null,
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function get(Endpoint $endpoint): array
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw ConfigurationException::missingApiKey();
        }

        $servers = array_merge([$this->baseUrl], $this->fallbackUrls);
        $lastFailure = new ConnectionException('No EPPO server configured.', $endpoint->path);

        foreach ($servers as $server) {
            try {
                return $this->attempt($endpoint, $server);
            } catch (ConnectionException|ServerException $e) {
                // Only infrastructure faults justify trying the next server.
                $lastFailure = $e;
            }
        }

        throw $lastFailure;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function attempt(Endpoint $endpoint, string $server): array
    {
        $url = $endpoint->url($server);
        $attempts = max(1, $this->retry['times']);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $this->throttle?->acquire();

            try {
                $response = $this->send($url);
            } catch (LaravelConnectionException $e) {
                if ($attempt < $attempts) {
                    $this->sleepFor($this->backoffMs($attempt));

                    continue;
                }

                $this->emit(new RequestFailed($endpoint, 0, $e->getMessage()));

                throw new ConnectionException(
                    sprintf('Could not reach EPPO at %s: %s', $server, $e->getMessage()),
                    $url,
                    $e,
                );
            }

            if ($response->successful()) {
                $this->emit(new RequestSucceeded($endpoint, $response->status()));

                return $this->decode($response, $url);
            }

            $exception = RequestException::fromResponse($response->status(), $url, $this->safeJson($response));

            $retryable = $exception instanceof RateLimitException || $exception instanceof ServerException;

            if ($retryable && $attempt < $attempts) {
                $this->sleepFor($this->delayFor($exception, $response, $attempt));

                continue;
            }

            $this->emit(new RequestFailed($endpoint, $response->status(), $exception->getMessage()));

            throw $exception;
        }

        // Unreachable: the loop either returns or throws.
        throw new ConnectionException(sprintf('EPPO request to %s exhausted all attempts.', $url), $url);
    }

    private function send(string $url): Response
    {
        return $this->http
            ->withHeaders([
                'X-Api-Key' => (string) $this->apiKey,
                'Accept' => 'application/json',
                'User-Agent' => $this->userAgent,
            ])
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->get($url);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(Response $response, string $url): array
    {
        $decoded = $response->json();

        if ($decoded === null && trim($response->body()) !== '') {
            throw new RequestException('EPPO returned a body that is not valid JSON.', $response->status(), $url);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeJson(Response $response): array
    {
        $decoded = $response->json();

        return is_array($decoded) ? $decoded : ['error' => trim($response->body())];
    }

    private function delayFor(RequestException $exception, Response $response, int $attempt): int
    {
        if ($exception instanceof RateLimitException && $exception->retryAfter !== null) {
            return min($exception->retryAfter * 1000, $this->retry['max_delay_ms']);
        }

        $header = $response->header('Retry-After');

        if ($header !== '' && is_numeric($header)) {
            return min(((int) $header) * 1000, $this->retry['max_delay_ms']);
        }

        return $this->backoffMs($attempt);
    }

    private function backoffMs(int $attempt): int
    {
        $delay = min($this->retry['base_delay_ms'] * (2 ** ($attempt - 1)), $this->retry['max_delay_ms']);

        if ($this->retry['jitter']) {
            $delay = random_int((int) ($delay / 2), (int) $delay);
        }

        return (int) $delay;
    }

    private function emit(object $event): void
    {
        Emit::event($event, $this->events);
    }

    private function sleepFor(int $milliseconds): void
    {
        usleep(max(0, $milliseconds) * 1000);
    }
}
