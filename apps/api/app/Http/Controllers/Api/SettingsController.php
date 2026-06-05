<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $tenantSettings = is_array($tenant->settings) ? $tenant->settings : [];
        $userPreferences = is_array($user->preferences) ? $user->preferences : [];

        return response()->json([
            'data' => [
                'tenant' => [
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'plan' => $tenant->plan,
                    'settings' => $this->resolveTenantSettings($tenantSettings),
                ],
                'preferences' => $this->resolveUserPreferences($userPreferences),
            ],
        ]);
    }

    public function updateTenant(Request $request): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->isTenantAdmin()) {
            return response()->json([
                'message' => 'You are not allowed to update tenant settings.',
            ], 403);
        }

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'timezone' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'max:10'],
            'registration_warning_days' => ['required', 'integer', 'min:1', 'max:90'],
            'service_due_soon_km' => ['required', 'integer', 'min:1', 'max:100000'],
            'allow_multiple_primary_assignments' => ['required', 'boolean'],
        ]);

        $settings = is_array($tenant->settings) ? $tenant->settings : [];

        $settings['company_name'] = $validated['company_name'];
        $settings['contact_email'] = $validated['contact_email'] ?? null;
        $settings['primary_color'] = $validated['primary_color'] ?? '#2563eb';
        $settings['timezone'] = $validated['timezone'];
        $settings['currency'] = strtoupper($validated['currency']);
        $settings['registration_warning_days'] = $validated['registration_warning_days'];
        $settings['service_due_soon_km'] = $validated['service_due_soon_km'];
        $settings['allow_multiple_primary_assignments'] = $validated['allow_multiple_primary_assignments'];

        $tenant->update([
            'name' => $validated['company_name'],
            'settings' => $settings,
        ]);

        return response()->json([
            'message' => 'Tenant settings updated successfully.',
            'data' => [
                'settings' => $this->resolveTenantSettings($settings),
            ],
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'language' => ['required', 'string', 'max:20'],
            'theme' => ['required', 'in:light,dark,system'],
        ]);

        $preferences = is_array($user->preferences) ? $user->preferences : [];

        $preferences['language'] = $validated['language'];
        $preferences['theme'] = $validated['theme'];

        $user->update([
            'preferences' => $preferences,
        ]);

        return response()->json([
            'message' => 'User preferences updated successfully.',
            'data' => [
                'preferences' => $this->resolveUserPreferences($preferences),
            ],
        ]);
    }

    private function resolveTenantSettings(array $settings): array
    {
        return [
            'company_name' => $settings['company_name'] ?? '',
            'contact_email' => $settings['contact_email'] ?? '',
            'primary_color' => $settings['primary_color'] ?? '#2563eb',
            'timezone' => $settings['timezone'] ?? 'Europe/Sarajevo',
            'currency' => $settings['currency'] ?? 'EUR',
            'registration_warning_days' => $settings['registration_warning_days'] ?? 7,
            'service_due_soon_km' => $settings['service_due_soon_km'] ?? 1000,
            'allow_multiple_primary_assignments' => (bool) ($settings['allow_multiple_primary_assignments'] ?? false),
        ];
    }

    private function resolveUserPreferences(array $preferences): array
    {
        return [
            'language' => $preferences['language'] ?? 'en',
            'theme' => $preferences['theme'] ?? 'light',
        ];
    }
}
