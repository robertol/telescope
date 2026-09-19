<?php

namespace Laravel\Telescope\Http\Middleware;

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
            || $request->is($path.'/telescope-api/login');
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
}
