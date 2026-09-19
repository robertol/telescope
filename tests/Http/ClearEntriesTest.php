<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Telescope\Database\Factories\EntryModelFactory;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\Console\CreatesTelescopeEntries;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class ClearEntriesTest extends FeatureTestCase
{
    use CreatesTelescopeEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_clear_endpoint_deletes_entries_and_monitoring(): void
    {
        $entry = EntryModelFactory::new()->create();

        DB::table('telescope_monitoring')->insert(['tag' => 'Auth:1']);
        DB::table('telescope_monitored_endpoints')->insert(['endpoint' => 'POST:/api/webhooks/*']);

        $this->deleteJson('/telescope/telescope-api/entries')
            ->assertSuccessful();

        $this->assertDatabaseMissing('telescope_entries', ['uuid' => $entry->uuid]);
        $this->assertSame(0, DB::table('telescope_monitoring')->count());
        $this->assertSame(0, DB::table('telescope_monitored_endpoints')->count());
    }

    public function test_clear_endpoint_can_keep_monitored_batches_tags_and_lists(): void
    {
        $batchId = (string) Str::uuid();

        DB::table('telescope_monitoring')->insert(['tag' => 'Auth:1']);
        DB::table('telescope_monitored_endpoints')->insert(['endpoint' => 'POST:/api/webhooks/*']);

        $request = $this->createRequest([
            'method' => 'POST',
            'uri' => '/api/webhooks/stripe',
        ], ['batch_id' => $batchId]);

        $query = $this->createQuery([], ['batch_id' => $batchId]);

        $tagged = EntryModelFactory::new()->create();
        DB::table('telescope_entries_tags')->insert([
            'entry_uuid' => $tagged->uuid,
            'tag' => 'Auth:1',
        ]);

        $unrelated = EntryModelFactory::new()->create();

        $this->deleteJson('/telescope/telescope-api/entries', [
            'preserve_monitoring' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('telescope_entries', ['uuid' => $request->uuid]);
        $this->assertDatabaseHas('telescope_entries', ['uuid' => $query->uuid]);
        $this->assertDatabaseHas('telescope_entries', ['uuid' => $tagged->uuid]);
        $this->assertDatabaseMissing('telescope_entries', ['uuid' => $unrelated->uuid]);
        $this->assertDatabaseHas('telescope_monitoring', ['tag' => 'Auth:1']);
        $this->assertDatabaseHas('telescope_monitored_endpoints', ['endpoint' => 'POST:/api/webhooks/*']);
    }

    public function test_clear_endpoint_refuses_when_too_many_entries_would_be_deleted(): void
    {
        config(['telescope.prune_request_limit' => 1]);

        $first = EntryModelFactory::new()->create();
        $second = EntryModelFactory::new()->create();

        $this->deleteJson('/telescope/telescope-api/entries')
            ->assertStatus(409);

        $this->assertDatabaseHas('telescope_entries', ['uuid' => $first->uuid]);
        $this->assertDatabaseHas('telescope_entries', ['uuid' => $second->uuid]);
    }
}
