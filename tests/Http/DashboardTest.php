<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\Console\CreatesTelescopeEntries;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class DashboardTest extends FeatureTestCase
{
    use CreatesTelescopeEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_dashboard_summarizes_recent_requests_exceptions_and_jobs(): void
    {
        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/user',
            'response_status' => 200,
            'duration' => 40,
        ]);

        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/slow',
            'response_status' => 500,
            'duration' => 1500,
        ]);

        $this->createException(['class' => 'RuntimeException', 'message' => 'Boom']);

        $this->createEntry(EntryType::JOB, ['status' => 'processed', 'name' => 'OkJob']);
        $this->createEntry(EntryType::JOB, ['status' => 'failed', 'name' => 'BadJob']);
        $this->createEntry(EntryType::JOB, ['status' => 'pending', 'name' => 'WaitJob']);

        $this->createRequest([
            'method' => 'GET',
            'uri' => '/old',
            'response_status' => 200,
            'duration' => 10,
        ], ['created_at' => now()->subDays(20)]);

        $response = $this->getJson('/telescope/telescope-api/dashboard?hours=336')
            ->assertSuccessful()
            ->assertJsonPath('requests.total', 2)
            ->assertJsonPath('requests.status.2xx', 1)
            ->assertJsonPath('requests.status.5xx', 1)
            ->assertJsonPath('exceptions.total', 1)
            ->assertJsonPath('jobs.processed', 1)
            ->assertJsonPath('jobs.failed', 1)
            ->assertJsonPath('jobs.pending', 1)
            ->assertJsonPath('slow_routes.0.uri', '/v1/slow');

        $buckets = $response->json('requests.buckets');
        $requestBucket = collect($buckets)->first(fn ($bucket) => ($bucket['total'] ?? 0) > 0);

        $this->assertGreaterThan(300, count($buckets));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:00:00$/', $buckets[0]['bucket']);
        $this->assertNotNull($requestBucket);
        $this->assertSame(1, $requestBucket['123xx']);
        $this->assertSame(0, $requestBucket['4xx']);
        $this->assertSame(1, $requestBucket['5xx']);
        $this->assertEqualsWithDelta(770, $requestBucket['avg'], 0.01);
        $this->assertEquals(1500, $requestBucket['p95']);

        $jobBucket = collect($response->json('jobs.buckets'))->first(fn ($bucket) => ($bucket['total'] ?? 0) > 0);

        $this->assertNotNull($jobBucket);
        $this->assertSame(1, $jobBucket['processed']);
        $this->assertSame(1, $jobBucket['pending']);
        $this->assertSame(1, $jobBucket['failed']);
    }
}
