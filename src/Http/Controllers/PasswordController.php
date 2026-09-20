<?php

namespace Laravel\Telescope\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Telescope\Telescope;

class PasswordController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = Telescope::dashboardUser($request);

        if (! $user) {
            return redirect()->guest($this->loginUrl());
        }

        if (! $user->must_change_password) {
            return redirect()->to($this->dashboardUrl());
        }

        return view('telescope::password');
    }

    public function update(Request $request): JsonResponse
    {
        $user = Telescope::dashboardUser($request);

        if (! $user) {
            abort(403);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                Rule::notIn([(string) config('telescope.auth.default.password', 'telescope')]),
            ],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Choose a password different from the current one.'],
            ]);
        }

        $user->password = $validated['password'];
        $user->must_change_password = false;
        $user->save();

        $request->session()->regenerate();

        return response()->json(['ok' => true]);
    }

    protected function loginUrl(): string
    {
        $path = trim((string) config('telescope.path'), '/');

        return ($path === '' ? '' : '/'.$path).'/login';
    }

    protected function dashboardUrl(): string
    {
        $path = trim((string) config('telescope.path'), '/');

        return $path === '' ? '/' : '/'.$path;
    }
}
