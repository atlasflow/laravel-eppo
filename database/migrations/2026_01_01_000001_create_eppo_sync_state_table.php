<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * How far `eppo:sync` has read the EPPO change feed. One row per feed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->schema()->create($this->table(), function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64)->unique();
            $table->timestamp('last_run_at')->nullable();
            $table->date('last_change_date')->nullable();
            $table->unsignedInteger('last_scanned')->default(0);
            $table->unsignedInteger('last_invalidated')->default(0);
            $table->unsignedInteger('runs')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('eppo.cache.durable.sync_table', 'eppo_sync_state');
    }

    private function schema(): Builder
    {
        return Schema::connection(config('eppo.cache.durable.connection'));
    }
};
