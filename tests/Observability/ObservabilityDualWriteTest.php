<?php

namespace Laravel\Telescope\Tests\Observability;

use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Observability\ObservabilityTraceContext;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\Tests\FeatureTestCase;

class ObservabilityDualWriteTest extends FeatureTestCase
{
    use MakesIncomingEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('telescope.observability.storage_enabled', true);
        $app['config']->set('telescope.observability.legacy_storage', true);
    }

    public function test_it_writes_specialized_rows_and_legacy_entries_when_both_flags_are_on(): void
    {
        $this->store($this->incomingRequest());

        $this->assertSame(1, DB::table('observability_requests')->count());
        $this->assertSame(1, DB::table('telescope_entries')->where('type', EntryType::REQUEST)->count());
    }

    public function test_it_does_not_write_observability_rows_when_storage_is_disabled(): void
    {
        config(['telescope.observability.storage_enabled' => false]);

        $this->store($this->incomingRequest());

        $this->assertSame(0, DB::table('observability_requests')->count());
        $this->assertSame(1, DB::table('telescope_entries')->count());
    }

    public function test_it_skips_legacy_storage_when_the_legacy_flag_is_off(): void
    {
        config(['telescope.observability.legacy_storage' => false]);

        $this->store($this->incomingRequest());

        $this->assertSame(1, DB::table('observability_requests')->count());
        $this->assertSame(0, DB::table('telescope_entries')->count());
    }

    public function test_it_inserts_one_statement_per_type_instead_of_n_plus_one_inserts(): void
    {
        $inserts = [];

        DB::listen(function ($query) use (&$inserts) {
            if (str_starts_with(strtolower($query->sql), 'insert into "observability_requests"')
                || str_starts_with(strtolower($query->sql), 'insert into observability_requests')) {
                $inserts[] = $query->sql;
            }
        });

        $this->store(
            $this->incomingRequest(['uri' => '/a']),
            $this->incomingRequest(['uri' => '/b']),
            $this->incomingRequest(['uri' => '/c']),
        );

        $this->assertCount(1, $inserts);
        $this->assertSame(3, DB::table('observability_requests')->count());
    }

    public function test_it_does_not_write_mail_entries_to_specialized_tables(): void
    {
        $this->store($this->incoming(EntryType::MAIL, ['html' => '<p>Hi</p>']));

        $this->assertSame(0, DB::table('observability_requests')->count());
        $this->assertSame(1, DB::table('telescope_entries')->where('type', EntryType::MAIL)->count());
    }

    public function test_recording_assigns_parent_child_spans_and_resets_between_requests(): void
    {
        Telescope::startRecording(false);
        Telescope::recordQuery($this->incomingQuery());
        Telescope::recordRequest($this->incomingRequest());
        $this->terminateTelescope();

        $firstTrace = DB::table('observability_requests')->value('trace_id');

        $this->assertNotEmpty($firstTrace);
        $queryParent = DB::table('observability_queries')
            ->where('query_hash', md5('select * from users where id = ?'))
            ->value('parent_span_id');
        $requestSpan = DB::table('observability_requests')->value('span_id');

        $this->assertSame($requestSpan, $queryParent);

        app(ObservabilityTraceContext::class)->reset();
        Telescope::flushEntries();

        Telescope::startRecording(false);
        Telescope::recordRequest($this->incomingRequest(['uri' => '/next']));
        $this->terminateTelescope();

        $traces = DB::table('observability_requests')->pluck('trace_id')->unique()->all();

        $this->assertCount(2, $traces);
    }

    protected function store(...$entries): void
    {
        app(EntriesRepository::class)->store(collect($entries));
    }
}
