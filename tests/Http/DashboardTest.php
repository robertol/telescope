<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\Console\CreatesTelescopeEntries;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
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

    public function test_dashboard_defaults_to_the_last_24_hours(): void
    {
        Cache::forget('telescope:dashboard:24');

        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/user',
            'response_status' => 200,
            'duration' => 40,
        ]);

        $response = $this->getJson('/telescope/telescope-api/dashboard')
            ->assertSuccessful()
            ->assertJsonPath('hours', 24)
            ->assertJsonPath('requests.total', 1);

        $buckets = $response->json('requests.buckets');

        $this->assertGreaterThan(20, count($buckets));
        $this->assertLessThan(30, count($buckets));
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

        $this->assertGreaterThan(300, count($response->json('exceptions.timeline')));

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

    public function test_dashboard_aggregation_does_not_select_raw_entry_content(): void
    {
        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/user',
            'response_status' => 200,
            'duration' => 40,
            'payload' => ['blob' => str_repeat('x', 2000)],
        ]);
        $this->createEntry(EntryType::JOB, ['status' => 'processed', 'name' => 'OkJob']);

        Cache::forget('telescope:dashboard:24');

        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        $this->getJson('/telescope/telescope-api/dashboard?hours=24')
            ->assertSuccessful()
            ->assertJsonPath('requests.total', 1)
            ->assertJsonPath('jobs.processed', 1);

        $entryQueries = collect($sql)->filter(
            fn ($query) => str_contains(strtolower($query), 'telescope_entries')
        );

        $this->assertNotEmpty($entryQueries->all());

        foreach ($entryQueries as $query) {
            $this->assertDoesNotMatchRegularExpression(
                '/\bselect\s+(?:[`"\[]?content[`"\]]?\s*,|\*)/i',
                $query,
                $query
            );
        }
    }

    #[WithConfig('telescope.watchers', [], defer: false)]
    public function test_dashboard_fresh_query_bypasses_cached_payload(): void
    {
        Cache::forget('telescope:dashboard:24');

        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/one',
            'response_status' => 200,
            'duration' => 40,
        ]);

        $cachedTotal = $this->getJson('/telescope/telescope-api/dashboard?hours=24')
            ->assertSuccessful()
            ->json('requests.total');

        $this->assertGreaterThanOrEqual(1, $cachedTotal);

        $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/two',
            'response_status' => 200,
            'duration' => 40,
        ]);

        $this->getJson('/telescope/telescope-api/dashboard?hours=24')
            ->assertSuccessful()
            ->assertJsonPath('requests.total', $cachedTotal);

        $this->getJson('/telescope/telescope-api/dashboard?hours=24&fresh=1')
            ->assertSuccessful()
            ->assertJsonPath('requests.total', $cachedTotal + 1);
    }
}
