<?php

namespace Laravel\Telescope\Tests\Storage;

use PHPUnit\Framework\TestCase;

class TelescopeSchemaMigrationTest extends TestCase
{
    public function test_creates_isolated_schema_without_moving_existing_tables()
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/database/migrations/2018_08_08_000000_create_telescope_schema.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString('CREATE SCHEMA IF NOT EXISTS', $source);
        $this->assertStringNotContainsString('ALTER TABLE', $source);
        $this->assertStringNotContainsString('SET SCHEMA', $source);
    }
}
