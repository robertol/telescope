<?php

namespace Laravel\Telescope\Tests\Observability;

use Laravel\Telescope\Observability\QueryFingerprint;
use PHPUnit\Framework\TestCase;

class QueryFingerprintTest extends TestCase
{
    public function test_parameterized_queries_with_different_literals_share_a_hash(): void
    {
        $fingerprint = new QueryFingerprint;

        $this->assertSame(
            $fingerprint->hash('SELECT * FROM users WHERE id = 123'),
            $fingerprint->hash('SELECT * FROM users WHERE id = 456')
        );
    }

    public function test_quoted_string_literals_are_normalized_before_hashing(): void
    {
        $fingerprint = new QueryFingerprint;

        $this->assertSame(
            $fingerprint->hash("SELECT * FROM users WHERE email = 'a@example.com'"),
            $fingerprint->hash("SELECT * FROM users WHERE email = 'b@example.com'")
        );
    }

    public function test_existing_watcher_hash_is_preferred(): void
    {
        $fingerprint = new QueryFingerprint;

        $this->assertSame(
            'watcher-hash',
            $fingerprint->hash('SELECT 1', 'watcher-hash')
        );
    }

    public function test_in_lists_of_different_length_are_not_forced_to_match(): void
    {
        $fingerprint = new QueryFingerprint;

        $this->assertNotSame(
            $fingerprint->hash('SELECT * FROM users WHERE id IN (1, 2)'),
            $fingerprint->hash('SELECT * FROM users WHERE id IN (1, 2, 3)')
        );
    }
}
