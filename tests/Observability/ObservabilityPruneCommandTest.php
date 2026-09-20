<?php

namespace Laravel\Telescope\Tests\Observability;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\Tests\FeatureTestCase;

class ObservabilityPruneCommandTest extends FeatureTestCase
{
    use MakesIncomingEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('telescope.observability.storage_enabled', true);
        $app['config']->set('telescope.observability.legacy_storage', false);
        $app['config']->set('telescope.observability.prune_chunk', 2);
        $app['config']->set('telescope.observability.retention.request', 30);
    }

    public function test_it_deletes_expired_rows_in_chunks_instead_of_one_unbounded_delete(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');

        foreach (range(1, 5) as $i) {
            $entry = $this->incomingRequest(['uri' => '/old-'.$i]);
            $entry->recordedAt = now()->subDays(40);
            app(EntriesRepository::class)->store(collect([$entry]));
        }

        $recent = $this->incomingRequest(['uri' => '/recent']);
        $recent->recordedAt = now();
        app(EntriesRepository::class)->store(collect([$recent]));

        $deletes = [];

        DB::listen(function ($query) use (&$deletes) {
            if (str_starts_with(strtolower($query->sql), 'delete')) {
                $deletes[] = strtolower($query->sql);
            }
        });

        $this->artisan('observability:prune')
            ->expectsOutputToContain('pruned')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('observability_requests')->count());
        $this->assertDatabaseHas('observability_requests', ['uri' => '/recent']);
        $this->assertGreaterThan(1, count($deletes));

        foreach ($deletes as $sql) {
            $this->assertTrue(
                str_contains($sql, 'limit') || str_contains($sql, ' in ('),
                $sql
            );
        }

        Carbon::setTestNow();
    }
}
