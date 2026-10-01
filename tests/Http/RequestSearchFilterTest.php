<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\Console\CreatesTelescopeEntries;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class RequestSearchFilterTest extends FeatureTestCase
{
    use CreatesTelescopeEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_requests_can_be_filtered_by_ip_without_using_tags(): void
    {
        $match = $this->createRequest([
            'uri' => '/v1/cards',
            'ip_address' => '203.0.113.10',
            'user' => ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com'],
        ]);

        $this->createRequest([
            'uri' => '/v1/other',
            'ip_address' => '198.51.100.20',
            'user' => ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ]);

        $this->postJson('/telescope/telescope-api/requests?ip=203.0.113.10')
            ->assertSuccessful()
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.id', $match->uuid);
    }

    public function test_requests_can_be_filtered_by_email_without_using_tags(): void
    {
        $match = $this->createRequest([
            'uri' => '/v1/cards',
            'ip_address' => '203.0.113.10',
            'user' => ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com'],
        ]);

        $this->createRequest([
            'uri' => '/v1/other',
            'ip_address' => '198.51.100.20',
            'user' => ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ]);

        $this->postJson('/telescope/telescope-api/requests?email=ada@example.com')
            ->assertSuccessful()
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.id', $match->uuid);
    }

    public function test_requests_can_be_filtered_by_partial_email_and_path_together(): void
    {
        $match = $this->createRequest([
            'uri' => '/v1/cards/42',
            'ip_address' => '203.0.113.10',
            'user' => ['id' => 1, 'name' => 'Ada', 'email' => 'ada.lovelace@example.com'],
        ]);

        $this->createRequest([
            'uri' => '/v1/cards/99',
            'ip_address' => '203.0.113.10',
            'user' => ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ]);

        $this->createRequest([
            'uri' => '/v1/users',
            'ip_address' => '203.0.113.10',
            'user' => ['id' => 1, 'name' => 'Ada', 'email' => 'ada.lovelace@example.com'],
        ]);

        $this->postJson('/telescope/telescope-api/requests?email=ada.lovelace&endpoint='.urlencode('/v1/cards/*'))
            ->assertSuccessful()
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.id', $match->uuid);
    }
}
