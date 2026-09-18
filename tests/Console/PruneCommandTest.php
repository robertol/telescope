<?php

namespace Laravel\Telescope\Tests\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Telescope\Database\Factories\EntryModelFactory;
use Laravel\Telescope\Tests\FeatureTestCase;

class PruneCommandTest extends FeatureTestCase
{
    use CreatesTelescopeEntries;

    public function test_prune_command_will_clear_old_records()
    {
        $recent = EntryModelFactory::new()->create(['created_at' => now()]);

        $old = EntryModelFactory::new()->create(['created_at' => now()->subDays(2)]);

        $this->artisan('telescope:prune')->expectsOutput('1 entries pruned.');

        $this->assertDatabaseHas('telescope_entries', ['uuid' => $recent->uuid]);

        $this->assertDatabaseMissing('telescope_entries', ['uuid' => $old->uuid]);
    }

    public function test_prune_command_can_vary_hours()
    {
        $recent = EntryModelFactory::new()->create(['created_at' => now()->subHours(5)]);

        $this->artisan('telescope:prune')->expectsOutput('0 entries pruned.');

        $this->artisan('telescope:prune', ['--hours' => 4])->expectsOutput('1 entries pruned.');

        $this->assertDatabaseMissing('telescope_entries', ['uuid' => $recent->uuid]);
    }

    public function test_prune_preserves_old_requests_that_match_monitored_endpoints()
    {
        DB::table('telescope_monitored_endpoints')->insert([
            ['endpoint' => 'POST:/api/webhooks/*'],
        ]);

        $monitored = $this->createRequest([
            'method' => 'POST',
            'uri' => '/api/webhooks/stripe',
        ], ['created_at' => now()->subDays(2)]);

        $unrelated = $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/admin/cards',
        ], ['created_at' => now()->subDays(2)]);

        $this->artisan('telescope:prune')->expectsOutput('1 entries pruned.');

        $this->assertDatabaseHas('telescope_entries', ['uuid' => $monitored->uuid]);
        $this->assertDatabaseMissing('telescope_entries', ['uuid' => $unrelated->uuid]);
    }

    public function test_prune_preserves_entries_in_the_same_batch_as_a_monitored_request()
    {
        $batchId = (string) Str::uuid();

        DB::table('telescope_monitored_endpoints')->insert([
            ['endpoint' => 'POST:/api/webhooks/*'],
        ]);

        $request = $this->createRequest([
            'method' => 'POST',
            'uri' => '/api/webhooks/stripe',
        ], ['created_at' => now()->subDays(2), 'batch_id' => $batchId]);

        $query = $this->createQuery([], [
            'created_at' => now()->subDays(2),
            'batch_id' => $batchId,
        ]);

        $exception = $this->createException([], [
            'created_at' => now()->subDays(2),
            'batch_id' => $batchId,
        ]);

        $unrelated = $this->createQuery([], ['created_at' => now()->subDays(2)]);

        $this->artisan('telescope:prune')->expectsOutput('1 entries pruned.');

        $this->assertDatabaseHas('telescope_entries', ['uuid' => $request->uuid]);
        $this->assertDatabaseHas('telescope_entries', ['uuid' => $query->uuid]);
        $this->assertDatabaseHas('telescope_entries', ['uuid' => $exception->uuid]);
        $this->assertDatabaseMissing('telescope_entries', ['uuid' => $unrelated->uuid]);
    }
}
