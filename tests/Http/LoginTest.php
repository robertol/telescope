<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class LoginTest extends FeatureTestCase
{
    /** {@inheritdoc} */
    #[\Override]
    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [
            TelescopeApplicationServiceProvider::class,
        ]);
    }

    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_workbench_starts_at_telescope_without_laravel_auto_login(): void
    {
        $config = \Symfony\Component\Yaml\Yaml::parseFile(dirname(__DIR__, 2).'/testbench.yaml');

        $this->assertSame('/telescope', $config['workbench']['start'] ?? null);
        $this->assertTrue(
            ! array_key_exists('user', $config['workbench'])
                || $config['workbench']['user'] === null,
            'Workbench auto-login must not target a Laravel user that may be missing from sqlite.'
        );
    }

    public function test_guest_is_redirected_to_the_login_screen(): void
    {
        Telescope::auth(function ($request) {
            return Gate::check('viewTelescope', [$request->user()]);
        });

        Gate::define('viewTelescope', function (?Authenticatable $user) {
            return $user !== null;
        });

        $this->get('/telescope')
            ->assertRedirect('/telescope/login');
    }

    public function test_dashboard_css_is_always_dark(): void
    {
        Telescope::$useDarkTheme = false;

        $css = Telescope::css()->toHtml();

        $this->assertTrue(
            str_contains($css, 'color-scheme:dark') || str_contains($css, 'color-scheme: dark'),
            'Dashboard CSS should force the dark color-scheme.'
        );
        $this->assertTrue(
            str_contains($css, '#0b0b0d'),
            'Dashboard CSS should use the Nightwatch dark background.'
        );
    }

    public function test_dashboard_css_is_responsive(): void
    {
        $css = Telescope::css()->toHtml();

        $this->assertFalse(
            (bool) preg_match('/html\{[^}]*min-width:\s*1280px/', $css),
            'Dashboard CSS should not lock the document to 1280px.'
        );
        $this->assertTrue(
            str_contains($css, '@media (max-width:') && str_contains($css, '.nw-sidebar'),
            'Dashboard CSS should include a mobile sidebar breakpoint.'
        );
    }

    public function test_valid_credentials_with_gate_access_the_dashboard(): void
    {
        $user = $this->makeUser();

        Telescope::auth(function ($request) {
            return Gate::check('viewTelescope', [$request->user()]);
        });

        Gate::define('viewTelescope', function (?Authenticatable $auth) use ($user) {
            return $auth && $auth->getAuthIdentifier() === $user->getAuthIdentifier();
        });

        $this->postJson('/telescope/telescope-api/login', [
            'email' => 'ada@example.com',
            'password' => 'secret',
        ])->assertSuccessful();

        $this->get('/telescope')->assertSuccessful();
    }

    public function test_login_is_rejected_when_the_gate_denies_access(): void
    {
        $this->makeUser();

        Telescope::auth(function ($request) {
            return Gate::check('viewTelescope', [$request->user()]);
        });

        Gate::define('viewTelescope', function () {
            return false;
        });

        $this->postJson('/telescope/telescope-api/login', [
            'email' => 'ada@example.com',
            'password' => 'secret',
        ])->assertStatus(403);

        $this->get('/telescope')->assertRedirect('/telescope/login');
    }

    private function makeUser(): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => Hash::make('secret'),
        ]);
        $user->save();

        return $user;
    }
}
