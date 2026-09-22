<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\Console\CreatesTelescopeEntries;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class IpRequestsFilterTest extends FeatureTestCase
{
    use CreatesTelescopeEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_requests_can_be_filtered_by_ip_address(): void
    {
        $fromTarget = $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/cards',
            'ip_address' => '203.0.113.10',
        ]);

        $this->createRequest([
            'method' => 'POST',
            'uri' => '/v1/login',
            'ip_address' => '203.0.113.10',
        ]);

        $fromOther = $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/cards',
            'ip_address' => '198.51.100.20',
        ]);

        $ids = collect($this->postJson('/telescope/telescope-api/requests?ip=203.0.113.10')
            ->assertSuccessful()
            ->assertJsonCount(2, 'entries')
            ->json('entries'))->pluck('id');

        $this->assertTrue($ids->contains($fromTarget->uuid));
        $this->assertFalse($ids->contains($fromOther->uuid));
    }

    public function test_requests_can_be_filtered_by_ipv6_address(): void
    {
        $fromTarget = $this->createRequest([
            'uri' => '/v1/ipv6',
            'ip_address' => '2001:db8::1',
        ]);

        $this->createRequest([
            'uri' => '/v1/local',
            'ip_address' => '127.0.0.1',
        ]);

        $this->postJson('/telescope/telescope-api/requests?ip='.urlencode('2001:db8::1'))
            ->assertSuccessful()
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.id', $fromTarget->uuid);
    }

    public function test_invalid_ip_does_not_filter_the_request_list(): void
    {
        $this->createRequest(['ip_address' => '203.0.113.10']);
        $this->createRequest(['ip_address' => '198.51.100.20']);

        $this->postJson('/telescope/telescope-api/requests?ip=not-an-ip')
            ->assertSuccessful()
            ->assertJsonCount(2, 'entries');
    }

    public function test_endpoints_summary_groups_distinct_routes_for_an_ip(): void
    {
        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/cards',
            'ip_address' => '203.0.113.10',
        ]);
        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/cards',
            'ip_address' => '203.0.113.10',
        ]);
        $this->createRequest([
            'method' => 'POST',
            'uri' => '/v1/login',
            'ip_address' => '203.0.113.10',
        ]);
        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/cards',
            'ip_address' => '198.51.100.20',
        ]);

        $this->getJson('/telescope/telescope-api/requests/endpoints?ip=203.0.113.10')
            ->assertSuccessful()
            ->assertJsonCount(2, 'endpoints')
            ->assertJsonPath('endpoints.0.method', 'GET')
            ->assertJsonPath('endpoints.0.uri', '/v1/cards')
            ->assertJsonPath('endpoints.0.count', 2)
            ->assertJsonPath('endpoints.1.method', 'POST')
            ->assertJsonPath('endpoints.1.uri', '/v1/login')
            ->assertJsonPath('endpoints.1.count', 1);
    }

    public function test_endpoints_summary_requires_a_valid_ip(): void
    {
        $this->getJson('/telescope/telescope-api/requests/endpoints')
            ->assertStatus(422);

        $this->getJson('/telescope/telescope-api/requests/endpoints?ip=not-an-ip')
            ->assertStatus(422);
    }
}
