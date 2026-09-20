<?php

namespace Laravel\Telescope\Storage;

class TelescopeConnectionFactory
{
    /**
     * Clone an application database connection for Telescope storage.
     *
     * PostgreSQL receives an isolated search_path. MySQL, MariaDB, SQLite, and
     * SQL Server keep the cloned driver options so Telescope is not pgsql-only.
     *
     * @param  array<string, mixed>  $connections
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>|null
     */
    public function make(array $connections, string $driver, array $overrides = [], string $searchPath = 'telescope'): ?array
    {
        $base = $connections[$driver] ?? $connections['sqlite'] ?? null;

        if (! is_array($base)) {
            return null;
        }

        $connection = $base;

        foreach ($overrides as $key => $value) {
            if ($value !== null) {
                $connection[$key] = $value;
            }
        }

        if (($connection['driver'] ?? '') === 'pgsql') {
            $connection['search_path'] = $this->isolatedSchema($searchPath);
        }

        return $connection;
    }

    /**
     * Resolve a schema name that is not public.
     */
    protected function isolatedSchema(string $searchPath): string
    {
        $schema = strtolower(trim(explode(',', $searchPath)[0]));

        if ($schema === '' || $schema === 'public' || preg_match('/^[a-z][a-z0-9_]*$/', $schema) !== 1) {
            return 'telescope';
        }

        return $schema;
    }
}
