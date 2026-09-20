<?php

namespace Laravel\Telescope\Tests\Observability;

use Laravel\Telescope\Observability\ExceptionFingerprint;
use PHPUnit\Framework\TestCase;

class ExceptionFingerprintTest extends TestCase
{
    public function test_same_class_file_and_line_share_a_hash(): void
    {
        $fingerprint = new ExceptionFingerprint;

        $this->assertSame(
            $fingerprint->hash('RuntimeException', '/app/Foo.php', 10),
            $fingerprint->hash('RuntimeException', '/app/Foo.php', 10)
        );
    }

    public function test_different_lines_produce_different_hashes(): void
    {
        $fingerprint = new ExceptionFingerprint;

        $this->assertNotSame(
            $fingerprint->hash('RuntimeException', '/app/Foo.php', 10),
            $fingerprint->hash('RuntimeException', '/app/Foo.php', 11)
        );
    }

    public function test_message_is_not_part_of_the_hash(): void
    {
        $fingerprint = new ExceptionFingerprint;

        $this->assertSame(
            $fingerprint->hash('RuntimeException', '/app/Foo.php', 10, 'one'),
            $fingerprint->hash('RuntimeException', '/app/Foo.php', 10, 'two')
        );
    }
}
