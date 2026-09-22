<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Cache;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\Console\CreatesTelescopeEntries;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

#[WithConfig('telescope.watchers', [], defer: false)]
class ResourceSummaryTest extends FeatureTestCase
{
    use CreatesTelescopeEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_request_summary_fills_hour_buckets_and_counts_entries(): void
    {
        Cache::forget('telescope:summary:request:24');

        $this->createRequest(['uri' => '/now']);
        $this->createRequest(['uri' => '/also-now']);
        $this->createRequest(['uri' => '/old'], ['created_at' => now()->subDays(3)]);
        $this->createQuery(['sql' => 'select 1']);

        $response = $this->getJson('/telescope/telescope-api/summaries/request')
            ->assertSuccessful()
            ->assertJsonPath('type', 'request')
            ->assertJsonPath('hours', 24)
            ->assertJsonPath('total', 2);

        $timeline = $response->json('timeline');

        $this->assertGreaterThan(20, count($timeline));
        $this->assertLessThan(30, count($timeline));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:00:00$/', $timeline[0]['bucket']);
        $this->assertSame(2, (int) collect($timeline)->sum('total'));
    }

    public function test_job_query_and_log_summaries_count_only_their_type(): void
    {
        Cache::forget('telescope:summary:job:24');
        Cache::forget('telescope:summary:query:24');
        Cache::forget('telescope:summary:log:24');

        $this->createEntry(EntryType::JOB, ['status' => 'processed', 'name' => 'OkJob']);
        $this->createQuery(['sql' => 'select 1']);
        $this->createQuery(['sql' => 'select 2']);
        $this->createEntry(EntryType::LOG, ['level' => 'error', 'message' => 'boom']);

        $this->getJson('/telescope/telescope-api/summaries/job')
            ->assertSuccessful()
            ->assertJsonPath('type', 'job')
            ->assertJsonPath('total', 1);

        $this->getJson('/telescope/telescope-api/summaries/query')
            ->assertSuccessful()
            ->assertJsonPath('type', 'query')
            ->assertJsonPath('total', 2);

        $this->getJson('/telescope/telescope-api/summaries/log')
            ->assertSuccessful()
            ->assertJsonPath('type', 'log')
            ->assertJsonPath('total', 1);
    }

    public function test_unknown_summary_type_returns_not_found(): void
    {
        $this->getJson('/telescope/telescope-api/summaries/mail')
            ->assertNotFound();
    }

    public function test_request_summary_fresh_query_bypasses_cache(): void
    {
        Cache::forget('telescope:summary:request:24');

        $this->createRequest(['uri' => '/one']);

        $this->getJson('/telescope/telescope-api/summaries/request')
            ->assertSuccessful()
            ->assertJsonPath('total', 1);

        $this->createRequest(['uri' => '/two']);

        $this->getJson('/telescope/telescope-api/summaries/request')
            ->assertSuccessful()
            ->assertJsonPath('total', 1);

        $this->getJson('/telescope/telescope-api/summaries/request?fresh=1')
            ->assertSuccessful()
            ->assertJsonPath('total', 2);
    }
}
