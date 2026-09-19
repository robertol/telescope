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
}
