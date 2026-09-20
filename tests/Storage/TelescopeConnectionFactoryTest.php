<?php

namespace Laravel\Telescope\Tests\Storage;

use Laravel\Telescope\Storage\TelescopeConnectionFactory;
use PHPUnit\Framework\TestCase;

class TelescopeConnectionFactoryTest extends TestCase
{
    public function test_package_config_defaults_to_telescope_connection()
    {
        $config = require dirname(__DIR__, 2).'/config/telescope.php';

        $this->assertSame('telescope', $config['storage']['database']['connection']);
        $this->assertSame('telescope', $config['storage']['database']['search_path']);
    }

    public function test_clones_mysql_without_postgres_search_path()
    {
        $connection = (new TelescopeConnectionFactory)->make($this->connections(), 'mysql');

        $this->assertSame('mysql', $connection['driver']);
        $this->assertSame('3306', $connection['port']);
        $this->assertArrayHasKey('unix_socket', $connection);
        $this->assertArrayHasKey('collation', $connection);
        $this->assertArrayNotHasKey('search_path', $connection);
    }

    public function test_clones_mariadb_without_postgres_search_path()
    {
        $connection = (new TelescopeConnectionFactory)->make($this->connections(), 'mariadb');

        $this->assertSame('mariadb', $connection['driver']);
        $this->assertArrayHasKey('unix_socket', $connection);
        $this->assertArrayNotHasKey('search_path', $connection);
    }

    public function test_clones_pgsql_with_schema_isolated_from_public()
    {
        $connection = (new TelescopeConnectionFactory)->make($this->connections(), 'pgsql');

        $this->assertSame('pgsql', $connection['driver']);
        $this->assertSame('telescope', $connection['search_path']);
        $this->assertNotSame('public', $connection['search_path']);
        $this->assertArrayHasKey('sslmode', $connection);
        $this->assertArrayNotHasKey('unix_socket', $connection);
    }

    public function test_does_not_store_on_the_application_pgsql_connection()
    {
        $factory = new TelescopeConnectionFactory;

        $this->assertSame('telescope', $factory->resolveStorageConnectionName('pgsql'));
        $this->assertSame('telescope', $factory->resolveStorageConnectionName('mysql'));
        $this->assertSame('telescope', $factory->resolveStorageConnectionName('mariadb'));
        $this->assertSame('sqlite', $factory->resolveStorageConnectionName('sqlite'));
        $this->assertSame('telescope', $factory->resolveStorageConnectionName('telescope'));
    }

    public function test_applies_overrides_without_forcing_pgsql()
    {
        $connection = (new TelescopeConnectionFactory)->make($this->connections(), 'mysql', [
            'host' => 'db.internal',
            'database' => 'telescope_mysql',
        ]);

        $this->assertSame('mysql', $connection['driver']);
        $this->assertSame('db.internal', $connection['host']);
        $this->assertSame('telescope_mysql', $connection['database']);
        $this->assertArrayNotHasKey('search_path', $connection);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function connections(): array
    {
        return [
            'mysql' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => 'app',
                'unix_socket' => '',
                'collation' => 'utf8mb4_unicode_ci',
                'strict' => true,
                'options' => [],
            ],
            'mariadb' => [
                'driver' => 'mariadb',
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => 'app',
                'unix_socket' => '',
                'collation' => 'utf8mb4_unicode_ci',
                'strict' => true,
                'options' => [],
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'port' => '5432',
                'database' => 'app',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ],
        ];
    }
}
