<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class FirstAccessPasswordTest extends FeatureTestCase
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
    protected function resolveApplicationCore($app)
    {
        parent::resolveApplicationCore($app);

        $app->detectEnvironment(function () {
            return 'local';
        });
    }

    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_local_dashboard_is_blocked_until_the_default_password_is_changed(): void
    {
        $this->assertTrue($this->app->environment('local'));

        $this->get('/telescope')->assertRedirect('/telescope/login');

        $this->postJson('/telescope/telescope-api/login', [
            'email' => 'telescope@local',
            'password' => 'telescope',
        ])->assertSuccessful()->assertJsonPath('must_change_password', true);

        $this->get('/telescope')->assertRedirect('/telescope/password');
        $this->getJson('/telescope/telescope-api/dashboard')->assertForbidden();
    }
}
