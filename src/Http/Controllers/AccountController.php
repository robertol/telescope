<?php

namespace Laravel\Telescope\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Laravel\Telescope\Storage\TelescopeUser;
use Laravel\Telescope\Telescope;

class AccountController extends Controller
{
    public function index(): JsonResponse
    {
        TelescopeUser::ensureDefault();

        return response()->json([
            'users' => TelescopeUser::query()->orderBy('id')->get(['id', 'name', 'email', 'created_at']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(TelescopeUser::class, 'email')],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = TelescopeUser::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'created_at']),
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = TelescopeUser::query()->findOrFail($id);

        if (TelescopeUser::query()->count() <= 1) {
            return response()->json([
                'message' => 'The last Telescope user cannot be deleted.',
            ], 422);
        }

        if ((int) $request->session()->get('telescope_user_id') === (int) $user->id) {
            return response()->json([
                'message' => 'You cannot delete the signed-in Telescope user.',
            ], 422);
        }

        $user->delete();

        return response()->json(['ok' => true]);
    }
}
