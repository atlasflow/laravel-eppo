<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * The durable cache. Designed to be kept, backed up and restored like any other
 * table — a warm store is worth more than the API calls it replaces.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->schema()->create($this->table(), function (Blueprint $table): void {
            $table->id();

            // sha1(cache version | path?query)
            $table->char('key', 40)->unique();
            $table->string('version', 32)->index();

            // Resource identifier ("taxon.hosts") and what it is about
            // ("BEMITA"). Together these give us tag-like invalidation without
            // needing a cache driver that supports tags.
            $table->string('resource', 64);
            $table->string('subject', 32)->nullable();

            $table->string('path', 255);
            $table->json('query')->nullable();

            // 200, or 404 for a cached absence.
            $table->unsignedSmallInteger('status')->default(200);

            $table->longText('payload')->nullable();
            $table->boolean('compressed')->default(false);
            $table->char('payload_hash', 40)->nullable();

            $table->timestamp('fetched_at');

            // When to revalidate. Null means never — the durable default.
            $table->timestamp('stale_at')->nullable();

            // When the row may be deleted. Null means keep forever.
            $table->timestamp('expires_at')->nullable();

            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();

            $table->timestamps();

            $table->index(['resource', 'subject']);
            $table->index('subject');
            $table->index('stale_at');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('eppo.cache.durable.table', 'eppo_cache_entries');
    }

    private function schema(): Builder
    {
        return Schema::connection(config('eppo.cache.durable.connection'));
    }
};
