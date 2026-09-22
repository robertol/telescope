<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Cache;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\Console\CreatesTelescopeEntries;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class SlowRequestsFilterTest extends FeatureTestCase
{
    use CreatesTelescopeEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_requests_can_be_filtered_to_those_above_the_duration_threshold(): void
    {
        $slow = $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/slow',
            'duration' => 1500,
        ]);

        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/fast',
            'duration' => 40,
        ]);

        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/edge',
            'duration' => 1000,
        ]);

        $this->postJson('/telescope/telescope-api/requests?min_duration=1000')
            ->assertSuccessful()
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.id', $slow->uuid);
    }

    public function test_dashboard_reports_the_slow_route_threshold_and_total_beyond_the_preview(): void
    {
        Cache::forget('telescope:dashboard:24');

        $this->createRequest(['method' => 'GET', 'uri' => '/a', 'duration' => 1100]);
        $this->createRequest(['method' => 'GET', 'uri' => '/b', 'duration' => 1200]);
        $this->createRequest(['method' => 'GET', 'uri' => '/c', 'duration' => 1300]);
        $this->createRequest(['method' => 'GET', 'uri' => '/d', 'duration' => 1400]);
        $this->createRequest(['method' => 'GET', 'uri' => '/e', 'duration' => 1500]);
        $this->createRequest(['method' => 'GET', 'uri' => '/fast', 'duration' => 20]);

        $this->getJson('/telescope/telescope-api/dashboard?hours=24')
            ->assertSuccessful()
            ->assertJsonPath('slow_route_threshold_ms', 1000)
            ->assertJsonPath('slow_routes_total', 5)
            ->assertJsonCount(4, 'slow_routes');
    }
}
