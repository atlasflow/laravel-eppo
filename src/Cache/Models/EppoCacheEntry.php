<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Cache\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * Optional Eloquent view over the durable cache table. The package itself uses
 * the query builder; this exists so applications can report on the cache with
 * the tools they already know.
 *
 * @property string $key
 * @property string $resource
 * @property string|null $subject
 * @property int $status
 * @property int $hits
 */
final class EppoCacheEntry extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'query' => 'array',
        'compressed' => 'bool',
        'fetched_at' => 'datetime',
        'stale_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_hit_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('eppo.cache.durable.table', 'eppo_cache_entries');
    }

    public function getConnectionName(): ?string
    {
        return config('eppo.cache.durable.connection');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeStale(Builder $query): void
    {
        $query->whereNotNull('stale_at')->where('stale_at', '<=', Date::now());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForSubject(Builder $query, string $subject): void
    {
        $query->where('subject', $subject);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForResource(Builder $query, string $resource): void
    {
        str_ends_with($resource, '*')
            ? $query->where('resource', 'like', str_replace('*', '%', $resource))
            : $query->where('resource', $resource);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeNegative(Builder $query): void
    {
        $query->where('status', 404);
    }
}
