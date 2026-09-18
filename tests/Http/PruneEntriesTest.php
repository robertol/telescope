<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Telescope\Database\Factories\EntryModelFactory;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class PruneEntriesTest extends FeatureTestCase
{
    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_prune_endpoint_deletes_entries_older_than_the_given_hours(): void
    {
        $recent = EntryModelFactory::new()->create(['created_at' => now()->subHours(2)]);
        $old = EntryModelFactory::new()->create(['created_at' => now()->subHours(10)]);

        $this->postJson('/telescope/telescope-api/entries/prune', ['hours' => 5])
            ->assertSuccessful()
            ->assertJson(['pruned' => 1]);

        $this->assertDatabaseHas('telescope_entries', ['uuid' => $recent->uuid]);
        $this->assertDatabaseMissing('telescope_entries', ['uuid' => $old->uuid]);
    }

    public function test_prune_endpoint_rejects_invalid_hours(): void
    {
        $this->postJson('/telescope/telescope-api/entries/prune', ['hours' => 0])
            ->assertStatus(422);
    }

    public function test_prune_endpoint_refuses_when_too_many_entries_would_be_deleted(): void
    {
        config(['telescope.prune_request_limit' => 1]);

        $first = EntryModelFactory::new()->create(['created_at' => now()->subHours(10)]);
        $second = EntryModelFactory::new()->create(['created_at' => now()->subHours(12)]);

        $this->postJson('/telescope/telescope-api/entries/prune', ['hours' => 5])
            ->assertStatus(409);

        $this->assertDatabaseHas('telescope_entries', ['uuid' => $first->uuid]);
        $this->assertDatabaseHas('telescope_entries', ['uuid' => $second->uuid]);
    }

    public function test_prune_status_reports_when_the_period_exceeds_the_safe_limit(): void
    {
        config(['telescope.prune_request_limit' => 1]);

        EntryModelFactory::new()->create(['created_at' => now()->subHours(10)]);

        $this->getJson('/telescope/telescope-api/entries/prune?hours=5')
            ->assertSuccessful()
            ->assertJson(['allowed' => true, 'limit' => 1]);

        EntryModelFactory::new()->create(['created_at' => now()->subHours(12)]);

        $this->getJson('/telescope/telescope-api/entries/prune?hours=5')
            ->assertSuccessful()
            ->assertJson(['allowed' => false, 'limit' => 1]);
    }
}
