<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;
use Workbench\App\Providers\TelescopeServiceProvider as WorkbenchTelescopeServiceProvider;

class WorkbenchLoginTest extends FeatureTestCase
{
    /** {@inheritdoc} */
    #[\Override]
    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [
            WorkbenchTelescopeServiceProvider::class,
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

    public function test_guest_is_redirected_to_login_in_the_local_workbench(): void
    {
        $this->assertTrue($this->app->environment('local'));

        $this->get('/telescope')->assertRedirect('/telescope/login');
        $this->get('/telescope/login')->assertSuccessful();
    }

    public function test_default_telescope_user_can_sign_in_on_the_local_workbench(): void
    {
        $this->postJson('/telescope/telescope-api/login', [
            'email' => 'telescope@local',
            'password' => 'telescope',
        ])->assertSuccessful()->assertJsonPath('ok', true);

        $this->get('/telescope')->assertSuccessful();
        $this->get('/telescope/login')->assertRedirect('/telescope');
    }
}
