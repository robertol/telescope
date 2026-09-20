<?php

namespace Laravel\Telescope\Tests\Observability;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\Observability\Storage\ObservabilityEntryQuery;
use Laravel\Telescope\Tests\FeatureTestCase;

class ObservabilityCursorTest extends FeatureTestCase
{
    use MakesIncomingEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('telescope.observability.storage_enabled', true);
        $app['config']->set('telescope.observability.legacy_storage', false);
    }

    public function test_it_paginates_with_a_keyset_instead_of_offset(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');

        foreach (range(1, 5) as $i) {
            Carbon::setTestNow(now()->addSecond());
            app(EntriesRepository::class)->store(collect([
                $this->incomingRequest(['uri' => '/v1/cards/'.$i, 'route' => 'cards.show']),
            ]));
        }

        $sql = [];

        DB::listen(function ($query) use (&$sql) {
            if (str_contains(strtolower($query->sql), 'observability_requests')) {
                $sql[] = strtolower($query->sql);
            }
        });

        $page = app(ObservabilityEntryQuery::class)->latest('observability_requests', [], null, 2);

        $this->assertCount(2, $page);
        $this->assertSame('/v1/cards/5', $page[0]->uri);
        $this->assertSame('/v1/cards/4', $page[1]->uri);

        $next = app(ObservabilityEntryQuery::class)->latest('observability_requests', [], [
            'created_at' => $page->last()->created_at,
            'id' => $page->last()->id,
        ], 2);

        $this->assertCount(2, $next);
        $this->assertSame('/v1/cards/3', $next[0]->uri);
        $this->assertSame('/v1/cards/2', $next[1]->uri);

        foreach ($sql as $statement) {
            $this->assertStringNotContainsString('offset', $statement);
        }

        Carbon::setTestNow();
    }

    public function test_it_filters_error_requests_without_reading_json_content(): void
    {
        app(EntriesRepository::class)->store(collect([
            $this->incomingRequest(['response_status' => 200, 'uri' => '/ok']),
            $this->incomingRequest(['response_status' => 500, 'uri' => '/boom']),
        ]));

        $sql = [];

        DB::listen(function ($query) use (&$sql) {
            $sql[] = strtolower($query->sql);
        });

        $rows = app(ObservabilityEntryQuery::class)->latest('observability_requests', [
            ['status_code', '>=', 500],
        ], null, 50);

        $this->assertCount(1, $rows);
        $this->assertSame('/boom', $rows[0]->uri);

        foreach ($sql as $statement) {
            $this->assertStringNotContainsString('content', $statement);
        }
    }
}
