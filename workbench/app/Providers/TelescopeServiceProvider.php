<?php

namespace Workbench\App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider as ServiceProvider;

class TelescopeServiceProvider extends ServiceProvider
{
    /**
     * Configure the Telescope authorization services.
     *
     * The workbench is `local`, but the dashboard still requires a signed-in
     * Telescope user (or a Laravel user that passes the gate).
     */
    protected function authorization()
    {
        $this->gate();

        Telescope::auth(function ($request) {
            return Gate::check('viewTelescope', [$request->user()]);
        });
    }

    /**
     * Register the Telescope gate.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewTelescope', function (?Authenticatable $user) {
            return $user !== null;
        });
    }
}
