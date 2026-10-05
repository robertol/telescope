<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\Console\CreatesTelescopeEntries;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class UsersTest extends FeatureTestCase
{
    use CreatesTelescopeEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_users_endpoint_lists_authenticated_users_from_entries(): void
    {
        $withUser = $this->createRequest([
            'user' => ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com'],
        ]);

        DB::table('telescope_entries_tags')->insert([
            'entry_uuid' => $withUser->uuid,
            'tag' => 'Auth:1',
        ]);

        $this->createRequest(['uri' => '/anonymous']);

        $this->getJson('/telescope/telescope-api/users?hours=336')
            ->assertSuccessful()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', '1')
            ->assertJsonPath('users.0.name', 'Ada')
            ->assertJsonPath('users.0.email', 'ada@example.com')
            ->assertJsonPath('users.0.requests', 1);
    }

    public function test_users_aggregation_does_not_use_max_on_uuid_columns(): void
    {
        $withUser = $this->createRequest([
            'user' => ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com'],
        ]);

        DB::table('telescope_entries_tags')->insert([
            'entry_uuid' => $withUser->uuid,
            'tag' => 'Auth:1',
        ]);

        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        $this->getJson('/telescope/telescope-api/users?hours=336')
            ->assertSuccessful()
            ->assertJsonCount(1, 'users');

        $aggregationQueries = collect($sql)->filter(
            fn ($query) => str_contains(strtolower($query), 'telescope_entries_tags')
                && str_contains(strtolower($query), 'group by')
        );

        $this->assertNotEmpty($aggregationQueries->all());

        foreach ($aggregationQueries as $query) {
            $this->assertDoesNotMatchRegularExpression(
                '/max\s*\(\s*(?:[`"\[]?entries[`"\]]?\.)?[`"\[]?uuid[`"\]]?\s*\)/i',
                $query,
                $query
            );
        }
    }

    public function test_users_endpoint_uses_latest_entry_content_for_user_profile(): void
    {
        $older = $this->createRequest([
            'user' => ['id' => 1, 'name' => 'Old Name', 'email' => 'old@example.com'],
        ], ['created_at' => now()->subHour()]);

        $newer = $this->createRequest([
            'user' => ['id' => 1, 'name' => 'New Name', 'email' => 'new@example.com'],
        ]);

        DB::table('telescope_entries_tags')->insert([
            ['entry_uuid' => $older->uuid, 'tag' => 'Auth:1'],
            ['entry_uuid' => $newer->uuid, 'tag' => 'Auth:1'],
        ]);

        $this->getJson('/telescope/telescope-api/users?hours=336')
            ->assertSuccessful()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.name', 'New Name')
            ->assertJsonPath('users.0.email', 'new@example.com')
            ->assertJsonPath('users.0.requests', 2);
    }
}
