<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Cache;

use Atlasflow\Eppo\Contracts\DurableStore;
use Atlasflow\Eppo\Http\Endpoint;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;

/**
 * The durable tier: a plain table, meant to outlive deploys, cache flushes and
 * Redis restarts. Nothing in here assumes tags, so it works on MySQL, Postgres
 * and SQLite alike.
 */
final class DatabaseStore implements DurableStore
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table,
        private readonly bool $compress = false,
    ) {}

    public function get(string $key): ?CacheEntry
    {
        $row = $this->query()->where('key', $key)->first();

        return $row === null ? null : $this->toEntry((array) $row);
    }

    public function put(CacheEntry $entry): void
    {
        $payload = $this->encode($entry->payload);

        $this->query()->updateOrInsert(
            ['key' => $entry->key],
            [
                'version' => $entry->version,
                'resource' => $entry->endpoint->resource,
                'subject' => $entry->endpoint->subject,
                'path' => $entry->endpoint->path,
                'query' => $entry->endpoint->query === [] ? null : json_encode($entry->endpoint->query),
                'status' => $entry->status,
                'payload' => $payload,
                'compressed' => $this->compress,
                'payload_hash' => $entry->hash(),
                'fetched_at' => $entry->fetchedAt->format('Y-m-d H:i:s'),
                'stale_at' => $entry->staleAt?->format('Y-m-d H:i:s'),
                'expires_at' => $entry->expiresAt?->format('Y-m-d H:i:s'),
                'updated_at' => Date::now(),
                'created_at' => Date::now(),
            ],
        );
    }

    public function forget(string $key): bool
    {
        return $this->query()->where('key', $key)->delete() > 0;
    }

    public function forgetSubject(string $subject): int
    {
        return $this->query()->where('subject', $subject)->delete();
    }

    public function forgetResource(string $resource): int
    {
        $query = $this->query();

        if (str_ends_with($resource, '*')) {
            $query->where('resource', 'like', str_replace('*', '%', $resource));
        } else {
            $query->where('resource', $resource);
        }

        return $query->delete();
    }

    public function flush(): int
    {
        $count = (int) $this->query()->count();

        $this->query()->delete();

        return $count;
    }

    public function prune(?string $currentVersion = null): int
    {
        $removed = $this->query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Date::now())
            ->delete();

        if ($currentVersion !== null) {
            $removed += $this->query()->where('version', '!=', $currentVersion)->delete();
        }

        return $removed;
    }

    public function recordHit(string $key): void
    {
        $this->query()->where('key', $key)->update([
            'hits' => $this->connection->raw('hits + 1'),
            'last_hit_at' => Date::now(),
        ]);
    }

    /**
     * @return array{entries: int, subjects: int, stale: int, bytes: int, oldest: ?string, newest: ?string}
     */
    public function stats(): array
    {
        $now = Date::now();

        return [
            'entries' => (int) $this->query()->count(),
            'subjects' => (int) $this->query()->whereNotNull('subject')->distinct()->count('subject'),
            'stale' => (int) $this->query()->whereNotNull('stale_at')->where('stale_at', '<=', $now)->count(),
            'bytes' => (int) $this->query()->sum($this->connection->raw('length(payload)')),
            'oldest' => $this->scalar($this->query()->min('fetched_at')),
            'newest' => $this->scalar($this->query()->max('fetched_at')),
        ];
    }

    /**
     * @return iterable<Endpoint>
     */
    public function endpoints(?string $resource = null, ?string $subject = null): iterable
    {
        $query = $this->query()->select('path', 'resource', 'subject', 'query')->orderBy('id');

        if ($resource !== null) {
            str_ends_with($resource, '*')
                ? $query->where('resource', 'like', str_replace('*', '%', $resource))
                : $query->where('resource', $resource);
        }

        if ($subject !== null) {
            $query->where('subject', $subject);
        }

        foreach ($query->cursor() as $row) {
            $row = (array) $row;

            yield new Endpoint(
                (string) $row['path'],
                (string) $row['resource'],
                $row['subject'] === null ? null : (string) $row['subject'],
                $this->decodeQuery($row['query'] ?? null),
            );
        }
    }

    /**
     * @return iterable<string>
     */
    public function subjects(): iterable
    {
        foreach ($this->query()->whereNotNull('subject')->distinct()->orderBy('subject')->pluck('subject') as $subject) {
            yield (string) $subject;
        }
    }

    private function query(): Builder
    {
        return $this->connection->table($this->table);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toEntry(array $row): CacheEntry
    {
        $endpoint = new Endpoint(
            (string) $row['path'],
            (string) $row['resource'],
            $row['subject'] === null ? null : (string) $row['subject'],
            $this->decodeQuery($row['query'] ?? null),
        );

        return new CacheEntry(
            key: (string) $row['key'],
            endpoint: $endpoint,
            payload: $this->decode($row['payload'], (bool) $row['compressed']),
            status: (int) $row['status'],
            version: (string) $row['version'],
            fetchedAt: new DateTimeImmutable((string) $row['fetched_at']),
            staleAt: $row['stale_at'] === null ? null : new DateTimeImmutable((string) $row['stale_at']),
            expiresAt: $row['expires_at'] === null ? null : new DateTimeImmutable((string) $row['expires_at']),
            hits: (int) ($row['hits'] ?? 0),
            payloadHash: $row['payload_hash'] === null ? null : (string) $row['payload_hash'],
        );
    }

    /**
     * @param  array<array-key, mixed>|null  $payload
     */
    private function encode(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->compress ? base64_encode((string) gzencode($json, 6)) : $json;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function decode(mixed $payload, bool $compressed): ?array
    {
        if ($payload === null) {
            return null;
        }

        $json = (string) $payload;

        if ($compressed) {
            $json = (string) gzdecode((string) base64_decode($json, true));
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, scalar>
     */
    private function decodeQuery(mixed $query): array
    {
        if ($query === null || $query === '') {
            return [];
        }

        $decoded = json_decode((string) $query, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function scalar(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
