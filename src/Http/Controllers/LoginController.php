<?php

namespace Laravel\Telescope\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Laravel\Telescope\Storage\TelescopeUser;
use Laravel\Telescope\Telescope;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        TelescopeUser::ensureDefault();

        $user = Telescope::dashboardUser(request());

        if ($user?->must_change_password) {
            return redirect()->to($this->passwordUrl());
        }

        if (TelescopeUser::requiresPasswordChange(request())) {
            return view('telescope::login');
        }

        if (Telescope::check(request())) {
            return redirect()->to('/'.trim((string) config('telescope.path'), '/'));
        }

        return view('telescope::login');
    }

    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        TelescopeUser::ensureDefault();

        $telescopeUser = TelescopeUser::query()->where('email', $credentials['email'])->first();

        if ($telescopeUser && Hash::check($credentials['password'], $telescopeUser->password)) {
            $request->session()->regenerate();
            $request->session()->put('telescope_user_id', $telescopeUser->id);

            return response()->json([
                'ok' => true,
                'must_change_password' => (bool) $telescopeUser->must_change_password,
            ]);
        }

        foreach ((array) config('telescope.auth.guards', ['web']) as $guard) {
            if (! Auth::guard($guard)->attempt($credentials, false)) {
                continue;
            }

            $user = Auth::guard($guard)->user();
            $request->setUserResolver(static fn () => $user);

            if (! Telescope::check($request)) {
                Auth::guard($guard)->logout();

                continue;
            }

            $request->session()->regenerate();

            return response()->json([
                'ok' => true,
                'must_change_password' => false,
            ]);
        }

        return response()->json([
            'message' => 'These credentials do not match our records.',
        ], 403);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->session()->forget('telescope_user_id');

        foreach ((array) config('telescope.auth.guards', ['web']) as $guard) {
            Auth::guard($guard)->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    protected function passwordUrl(): string
    {
        $path = trim((string) config('telescope.path'), '/');

        return ($path === '' ? '' : '/'.$path).'/password';
    }
}
