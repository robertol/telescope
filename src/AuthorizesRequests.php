<?php

namespace Laravel\Telescope;

use Laravel\Telescope\Storage\TelescopeUser;

trait AuthorizesRequests
{
    /**
     * The callback that should be used to authenticate Telescope users.
     *
     * @var \Closure
     */
    public static $authUsing;

    /**
     * Register the Telescope authentication callback.
     *
     * @param  \Closure  $callback
     * @return static
     */
    public static function auth($callback)
    {
        static::$authUsing = $callback;

        return new static;
    }

    /**
     * Determine if the given request can access the Telescope dashboard.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function check($request)
    {
        if (static::dashboardUser($request)) {
            return true;
        }

        return (static::$authUsing ?: function () {
            return app()->environment('local');
        })($request);
    }

    /**
     * The signed-in Telescope dashboard user, if any.
     */
    public static function dashboardUser($request): ?TelescopeUser
    {
        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get('telescope_user_id');

        if (! $id) {
            return null;
        }

        return TelescopeUser::query()->find($id);
    }
}
