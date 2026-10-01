<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\Console\CreatesTelescopeEntries;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class JourneyTest extends FeatureTestCase
{
    use CreatesTelescopeEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_journey_by_email_lists_endpoints_and_request_count(): void
    {
        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/cards',
            'ip_address' => '203.0.113.10',
            'user' => ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com'],
        ]);

        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/cards',
            'ip_address' => '203.0.113.10',
            'user' => ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com'],
        ]);

        $this->createRequest([
            'method' => 'POST',
            'uri' => '/v1/transfers',
            'ip_address' => '203.0.113.10',
            'user' => ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com'],
        ]);

        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/other',
            'ip_address' => '198.51.100.20',
            'user' => ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ]);

        $this->getJson('/telescope/telescope-api/journey?email=ada@example.com&hours=24')
            ->assertSuccessful()
            ->assertJsonPath('requests', 3)
            ->assertJsonCount(2, 'endpoints')
            ->assertJsonPath('endpoints.0.method', 'GET')
            ->assertJsonPath('endpoints.0.uri', '/v1/cards')
            ->assertJsonPath('endpoints.0.count', 2)
            ->assertJsonPath('endpoints.1.method', 'POST')
            ->assertJsonPath('endpoints.1.uri', '/v1/transfers')
            ->assertJsonPath('endpoints.1.count', 1);
    }

    public function test_journey_by_ip_lists_endpoints_without_auth_user(): void
    {
        $this->createRequest([
            'method' => 'GET',
            'uri' => '/health',
            'ip_address' => '203.0.113.10',
        ]);

        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/cards',
            'ip_address' => '203.0.113.10',
        ]);

        $this->createRequest([
            'method' => 'GET',
            'uri' => '/health',
            'ip_address' => '198.51.100.20',
        ]);

        $this->getJson('/telescope/telescope-api/journey?ip=203.0.113.10&hours=24')
            ->assertSuccessful()
            ->assertJsonPath('requests', 2)
            ->assertJsonCount(2, 'endpoints');
    }

    public function test_journey_requires_email_or_ip(): void
    {
        $this->getJson('/telescope/telescope-api/journey?hours=24')
            ->assertStatus(422);
    }
}
