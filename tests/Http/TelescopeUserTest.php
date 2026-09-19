<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Laravel\Telescope\Storage\TelescopeUser;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class TelescopeUserTest extends FeatureTestCase
{
    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_default_telescope_user_can_sign_in_without_a_laravel_user(): void
    {
        $this->assertSame(0, $this->laravelUsersCount());
        $this->assertTrue(TelescopeUser::query()->where('email', 'telescope@local')->exists());

        $this->postJson('/telescope/telescope-api/login', [
            'email' => 'telescope@local',
            'password' => 'telescope',
        ])->assertSuccessful()->assertJsonPath('ok', true);

        $this->get('/telescope')->assertSuccessful();
    }

    public function test_guest_cannot_create_telescope_users(): void
    {
        $this->postJson('/telescope/telescope-api/accounts', [
            'name' => 'Ada',
            'email' => 'ada@telescope.local',
            'password' => 'secret-pass',
        ])->assertForbidden();

        $this->assertFalse(TelescopeUser::query()->where('email', 'ada@telescope.local')->exists());
    }

    public function test_signed_in_telescope_user_can_create_another_account(): void
    {
        $this->loginAsDefaultTelescopeUser();

        $this->postJson('/telescope/telescope-api/accounts', [
            'name' => 'Ada',
            'email' => 'ada@telescope.local',
            'password' => 'secret-pass',
        ])
            ->assertSuccessful()
            ->assertJsonPath('user.email', 'ada@telescope.local')
            ->assertJsonMissingPath('user.password');

        $this->assertTrue(Hash::check(
            'secret-pass',
            TelescopeUser::query()->where('email', 'ada@telescope.local')->value('password')
        ));
    }

    public function test_created_telescope_user_can_sign_in(): void
    {
        $this->loginAsDefaultTelescopeUser();

        $this->postJson('/telescope/telescope-api/accounts', [
            'name' => 'Ada',
            'email' => 'ada@telescope.local',
            'password' => 'secret-pass',
        ])->assertSuccessful();

        $this->postJson('/telescope/telescope-api/logout')->assertSuccessful();

        $this->get('/telescope')->assertRedirect('/telescope/login');

        $this->postJson('/telescope/telescope-api/login', [
            'email' => 'ada@telescope.local',
            'password' => 'secret-pass',
        ])->assertSuccessful();

        $this->get('/telescope')->assertSuccessful();
    }

    public function test_cannot_delete_the_last_telescope_user(): void
    {
        $this->loginAsDefaultTelescopeUser();

        $id = TelescopeUser::query()->where('email', 'telescope@local')->value('id');

        $this->deleteJson('/telescope/telescope-api/accounts/'.$id)
            ->assertStatus(422);

        $this->assertTrue(TelescopeUser::query()->where('email', 'telescope@local')->exists());
    }

    public function test_dashboard_script_includes_the_signed_in_telescope_user(): void
    {
        $this->loginAsDefaultTelescopeUser();

        $html = $this->get('/telescope')->assertSuccessful()->getContent();

        $this->assertTrue(str_contains($html, 'telescope@local'));
        $this->assertTrue(
            str_contains($html, '\u0022user\u0022:{') || str_contains($html, '"user":{')
        );
        $this->assertTrue(str_contains($html, 'nw-sidebar-toggle'));
        $this->assertTrue(str_contains($html, 'nw-sidebar-backdrop'));
    }

    private function loginAsDefaultTelescopeUser(): void
    {
        $this->postJson('/telescope/telescope-api/login', [
            'email' => 'telescope@local',
            'password' => 'telescope',
        ])->assertSuccessful();
    }

    private function laravelUsersCount(): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('users')) {
            return 0;
        }

        return (int) \Illuminate\Support\Facades\DB::table('users')->count();
    }
}
