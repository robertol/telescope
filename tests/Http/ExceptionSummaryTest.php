<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\Console\CreatesTelescopeEntries;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class ExceptionSummaryTest extends FeatureTestCase
{
    use CreatesTelescopeEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_exception_summary_groups_families_and_counts_users(): void
    {
        $open = $this->createException([
            'class' => 'RuntimeException',
            'message' => 'Open',
            'resolved_at' => null,
        ], ['family_hash' => 'fam-open', 'created_at' => now()->subMinute()]);

        $latestOpen = $this->createException([
            'class' => 'RuntimeException',
            'message' => 'Open again',
            'resolved_at' => null,
        ], ['family_hash' => 'fam-open', 'created_at' => now()]);

        $this->createException([
            'class' => 'LogicException',
            'message' => 'Closed',
            'resolved_at' => now()->toDateTimeString(),
        ], ['family_hash' => 'fam-closed']);

        DB::table('telescope_entries_tags')->insert([
            'entry_uuid' => $open->uuid,
            'tag' => 'Auth:9',
        ]);

        $this->getJson('/telescope/telescope-api/exceptions/summary?hours=336')
            ->assertSuccessful()
            ->assertJsonPath('unhandled', 1)
            ->assertJsonPath('handled', 1)
            ->assertJsonCount(2, 'families');

        $this->getJson('/telescope/telescope-api/exceptions/summary?hours=336&status=unhandled')
            ->assertSuccessful()
            ->assertJsonCount(1, 'families')
            ->assertJsonPath('families.0.users', 1)
            ->assertJsonPath('families.0.latest_id', $latestOpen->uuid);
    }

    public function test_exception_summary_timeline_fills_empty_hour_buckets_across_the_period(): void
    {
        $this->createException([
            'class' => 'RuntimeException',
            'message' => 'Now',
        ], ['family_hash' => 'fam-now', 'created_at' => now()]);

        $this->createException([
            'class' => 'RuntimeException',
            'message' => 'Earlier',
        ], ['family_hash' => 'fam-earlier', 'created_at' => now()->subHours(40)]);

        $timeline = $this->getJson('/telescope/telescope-api/exceptions/summary?hours=336')
            ->assertSuccessful()
            ->json('timeline');

        $this->assertGreaterThan(300, count($timeline));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:00:00$/', $timeline[0]['bucket']);
        $this->assertSame(2, (int) collect($timeline)->sum('total'));
        $this->assertGreaterThan(
            300,
            \Carbon\Carbon::parse($timeline[0]['bucket'])->diffInHours(
                \Carbon\Carbon::parse($timeline[count($timeline) - 1]['bucket'])
            )
        );
    }

    public function test_exception_show_includes_impact(): void
    {
        $entry = $this->createException([
            'class' => 'RuntimeException',
            'message' => 'HTTP request returned status code 504: gateway',
            'hostname' => 'web-01',
        ], ['family_hash' => 'fam-impact']);

        $this->getJson('/telescope/telescope-api/exceptions/'.$entry->uuid)
            ->assertSuccessful()
            ->assertJsonPath('impact.first_seen', $entry->created_at->toDateTimeString())
            ->assertJsonPath('impact.last_seen', $entry->created_at->toDateTimeString())
            ->assertJsonPath('impact.events_24h', 1)
            ->assertJsonPath('impact.hostnames.0', 'web-01');
    }
}
