<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return config('telescope.storage.database.connection');
    }

    /**
     * Run the migrations.
     *
     * PostgreSQL only: create an isolated schema. Tables are created there by
     * later Telescope migrations via search_path. Existing public tables are
     * left untouched — relocating them deadlocks when Telescope is still writing.
     */
    public function up(): void
    {
        $connection = Schema::connection($this->getConnection())->getConnection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement(sprintf(
            'CREATE SCHEMA IF NOT EXISTS %s',
            $this->quoteIdent($this->schemaName())
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = Schema::connection($this->getConnection())->getConnection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement(sprintf(
            'DROP SCHEMA IF EXISTS %s',
            $this->quoteIdent($this->schemaName())
        ));
    }

    private function schemaName(): string
    {
        $connectionName = $this->getConnection();
        $searchPath = (string) config("database.connections.{$connectionName}.search_path", 'telescope');
        $schema = strtolower(trim(explode(',', $searchPath)[0]));

        if (in_array($schema, ['', 'public'], true) || preg_match('/^[a-z][a-z0-9_]*$/', $schema) !== 1) {
            return 'telescope';
        }

        return $schema;
    }

    private function quoteIdent(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
};
