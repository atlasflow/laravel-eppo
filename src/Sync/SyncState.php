<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Sync;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Date;

/**
 * A single row remembering how far `eppo:sync` has read the EPPO change feed.
 */
final class SyncState
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table,
        private readonly string $name = 'taxons',
    ) {}

    public function lastChangeDate(): ?DateTimeImmutable
    {
        $value = $this->connection->table($this->table)->where('name', $this->name)->value('last_change_date');

        return $value === null ? null : new DateTimeImmutable((string) $value);
    }

    public function lastRunAt(): ?DateTimeImmutable
    {
        $value = $this->connection->table($this->table)->where('name', $this->name)->value('last_run_at');

        return $value === null ? null : new DateTimeImmutable((string) $value);
    }

    public function record(SyncResult $result): void
    {
        $existing = $this->connection->table($this->table)->where('name', $this->name)->first();

        $values = [
            'last_run_at' => $result->ranAt->format('Y-m-d H:i:s'),
            'last_change_date' => $result->ranAt->format('Y-m-d'),
            'last_scanned' => $result->scanned,
            'last_invalidated' => $result->invalidatedEntries,
            'runs' => (int) ($existing->runs ?? 0) + 1,
            'updated_at' => Date::now(),
        ];

        if ($existing === null) {
            $values['created_at'] = Date::now();
        }

        $this->connection->table($this->table)->updateOrInsert(['name' => $this->name], $values);
    }

    public function runs(): int
    {
        return (int) $this->connection->table($this->table)->where('name', $this->name)->value('runs');
    }

    public function reset(): void
    {
        $this->connection->table($this->table)->where('name', $this->name)->delete();
    }
}
