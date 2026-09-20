<?php

namespace Laravel\Telescope\Tests\Observability;

use Laravel\Telescope\Observability\ObservabilitySanitizer;
use PHPUnit\Framework\TestCase;

class ObservabilitySanitizerTest extends TestCase
{
    public function test_it_redacts_sensitive_keys_recursively(): void
    {
        $sanitizer = new ObservabilitySanitizer;

        $result = $sanitizer->sanitize([
            'Authorization' => 'Bearer secret-token',
            'headers' => [
                'cookie' => 'session=abc',
                'Set-Cookie' => 'session=abc',
            ],
            'payload' => [
                'password' => 'secret',
                'password_confirmation' => 'secret',
                'token' => 't1',
                'access_token' => 'a1',
                'refresh_token' => 'r1',
                'api_key' => 'k1',
                'secret' => 's1',
                'cvv' => '123',
                'card_number' => '4111111111111111',
                'email' => 'user@example.com',
            ],
        ]);

        $this->assertSame('********', $result['Authorization']);
        $this->assertSame('********', $result['headers']['cookie']);
        $this->assertSame('********', $result['headers']['Set-Cookie']);
        $this->assertSame('********', $result['payload']['password']);
        $this->assertSame('********', $result['payload']['password_confirmation']);
        $this->assertSame('********', $result['payload']['token']);
        $this->assertSame('********', $result['payload']['access_token']);
        $this->assertSame('********', $result['payload']['refresh_token']);
        $this->assertSame('********', $result['payload']['api_key']);
        $this->assertSame('********', $result['payload']['secret']);
        $this->assertSame('********', $result['payload']['cvv']);
        $this->assertSame('********', $result['payload']['card_number']);
        $this->assertSame('user@example.com', $result['payload']['email']);
    }

    public function test_it_redacts_pan_like_values_even_without_a_sensitive_key(): void
    {
        $sanitizer = new ObservabilitySanitizer;

        $result = $sanitizer->sanitize([
            'note' => '4111111111111111',
            'safe' => 'order-42',
        ]);

        $this->assertSame('********', $result['note']);
        $this->assertSame('order-42', $result['safe']);
    }

    public function test_it_does_not_treat_hex_hashes_as_pan(): void
    {
        $sanitizer = new ObservabilitySanitizer;
        $hash = md5('select * from users where id = ?');

        $this->assertSame($hash, $sanitizer->sanitize(['hash' => $hash])['hash']);
    }
}
