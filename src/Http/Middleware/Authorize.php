<?php

namespace Laravel\Telescope\Http\Middleware;

use Laravel\Telescope\Storage\TelescopeUser;
use Laravel\Telescope\Telescope;

class Authorize
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, $next)
    {
        if ($this->isPublic($request)) {
            return $next($request);
        }

        if ($this->isPasswordChange($request)) {
            if (Telescope::dashboardUser($request)) {
                return $next($request);
            }

            if ($this->expectsJson($request)) {
                abort(403);
            }

            return redirect()->guest($this->loginUrl());
        }

        if (TelescopeUser::requiresPasswordChange($request)) {
            if ($this->expectsJson($request)) {
                abort(403);
            }

            return redirect()->to(
                Telescope::dashboardUser($request) ? $this->passwordUrl() : $this->loginUrl()
            );
        }

        if (Telescope::check($request)) {
            return $next($request);
        }

        if ($this->expectsJson($request)) {
            abort(403);
        }

        return redirect()->guest($this->loginUrl());
    }

    protected function isPublic($request): bool
    {
        $path = trim((string) config('telescope.path'), '/');

        return $request->is($path.'/login')
            || $request->is($path.'/telescope-api/login')
            || $request->is($path.'/telescope-api/logout');
    }

    protected function isPasswordChange($request): bool
    {
        $path = trim((string) config('telescope.path'), '/');

        return $request->is($path.'/password')
            || $request->is($path.'/telescope-api/password');
    }

    protected function expectsJson($request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || $request->is('*/telescope-api/*');
    }

    protected function loginUrl(): string
    {
        $path = trim((string) config('telescope.path'), '/');

        return ($path === '' ? '' : '/'.$path).'/login';
    }

    protected function passwordUrl(): string
    {
        $path = trim((string) config('telescope.path'), '/');

        return ($path === '' ? '' : '/'.$path).'/password';
    }
}
