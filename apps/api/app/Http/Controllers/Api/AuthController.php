<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $tenant = current_tenant();

        if (! $tenant || ! $tenant->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Tenant is not available.'],
            ]);
        }

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        Auth::login($user, (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => $this->transformUser($user),
            'tenant' => $this->transformTenant($tenant),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = current_tenant();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $tenant || ! $tenant->is_active || ! $user->canAccessTenant($tenant->id)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'user' => $this->transformUser($user),
            'tenant' => $this->transformTenant($tenant),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    private function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at,
        ];
    }

    private function transformTenant($tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'plan' => $tenant->plan,
            'is_active' => $tenant->is_active,
            'settings' => $tenant->settings,
        ];
    }
}
