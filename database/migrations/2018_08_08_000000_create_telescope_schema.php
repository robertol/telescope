<?php

use Illuminate\Database\Connection;
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
     */
    public function up(): void
    {
        $connection = Schema::connection($this->getConnection())->getConnection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $schema = $this->schemaName();
        $quotedSchema = $this->quoteIdent($schema);

        $connection->statement("CREATE SCHEMA IF NOT EXISTS {$quotedSchema}");

        foreach ($this->tables() as $table) {
            if (! $this->tableExistsInSchema($connection, 'public', $table)) {
                continue;
            }

            if ($this->tableExistsInSchema($connection, $schema, $table)) {
                continue;
            }

            $connection->statement(sprintf(
                'ALTER TABLE %s.%s SET SCHEMA %s',
                $this->quoteIdent('public'),
                $this->quoteIdent($table),
                $quotedSchema
            ));
        }
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

        $schema = $this->schemaName();

        foreach ($this->tables() as $table) {
            if (! $this->tableExistsInSchema($connection, $schema, $table)) {
                continue;
            }

            if ($this->tableExistsInSchema($connection, 'public', $table)) {
                continue;
            }

            $connection->statement(sprintf(
                'ALTER TABLE %s.%s SET SCHEMA %s',
                $this->quoteIdent($schema),
                $this->quoteIdent($table),
                $this->quoteIdent('public')
            ));
        }

        $connection->statement(sprintf(
            'DROP SCHEMA IF EXISTS %s',
            $this->quoteIdent($schema)
        ));
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        return [
            'telescope_entries_tags',
            'telescope_entries',
            'telescope_monitoring',
            'telescope_monitored_endpoints',
            'telescope_users',
        ];
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

    private function tableExistsInSchema(Connection $connection, string $schema, string $table): bool
    {
        $result = $connection->selectOne(
            'select 1 from information_schema.tables where table_schema = ? and table_name = ?',
            [$schema, $table]
        );

        return $result !== null;
    }
};
