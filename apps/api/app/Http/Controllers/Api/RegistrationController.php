<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:valid,expiring,expired,missing'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 10;
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;

        $today = now()->startOfDay();
        $soonDate = now()->startOfDay()->addDays(30);

        $registrations = Vehicle::query()
            ->where('tenant_id', $tenant->id)
            ->when($search, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('license_plate', 'like', "%{$search}%")
                        ->orWhere('vin', 'like', "%{$search}%");
                });
            })
            ->when($status === 'missing', fn ($query) => $query->whereNull('registration_expiry_date'))
            ->when($status === 'expired', fn ($query) => $query->whereNotNull('registration_expiry_date')->whereDate('registration_expiry_date', '<', $today))
            ->when($status === 'expiring', fn ($query) => $query
                ->whereNotNull('registration_expiry_date')
                ->whereDate('registration_expiry_date', '>=', $today)
                ->whereDate('registration_expiry_date', '<=', $soonDate))
            ->when($status === 'valid', fn ($query) => $query
                ->whereNotNull('registration_expiry_date')
                ->whereDate('registration_expiry_date', '>', $soonDate))
            ->orderByRaw('registration_expiry_date IS NULL ASC')
            ->orderBy('registration_expiry_date')
            ->paginate($perPage)
            ->through(fn (Vehicle $vehicle) => $this->transformRegistration($vehicle));

        return response()->json($registrations);
    }

    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ((int) $vehicle->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'message' => 'Registration record not found.',
            ], 404);
        }

        return response()->json([
            'data' => $this->transformRegistration($vehicle),
        ]);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ((int) $vehicle->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'message' => 'Registration record not found.',
            ], 404);
        }

        if (! $user->canManageTenantData()) {
            return response()->json([
                'message' => 'You are not allowed to update registrations.',
            ], 403);
        }

        $validated = $request->validate([
            'registration_expiry_date' => ['nullable', 'date'],
        ]);

        $vehicle->update([
            'registration_expiry_date' => $validated['registration_expiry_date'] ?? null,
        ]);

        return response()->json([
            'message' => 'Registration updated successfully.',
            'data' => $this->transformRegistration($vehicle->fresh()),
        ]);
    }

    private function transformRegistration(Vehicle $vehicle): array
    {
        $daysLeft = null;

        if ($vehicle->registration_expiry_date !== null) {
            $daysLeft = now()->startOfDay()->diffInDays($vehicle->registration_expiry_date, false);
        }

        return [
            'vehicle_id' => $vehicle->id,
            'name' => $vehicle->name,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'license_plate' => $vehicle->license_plate,
            'vin' => $vehicle->vin,
            'status' => $vehicle->status,
            'current_mileage' => (float) $vehicle->current_mileage,
            'registration_expiry_date' => $vehicle->registration_expiry_date?->toDateString(),
            'registration_status' => $this->resolveRegistrationStatus($vehicle),
            'days_left' => $daysLeft,
        ];
    }

    private function resolveRegistrationStatus(Vehicle $vehicle): string
    {
        if ($vehicle->registration_expiry_date === null) {
            return 'missing';
        }

        $daysLeft = now()->startOfDay()->diffInDays($vehicle->registration_expiry_date, false);

        if ($daysLeft < 0) {
            return 'expired';
        }

        if ($daysLeft <= 30) {
            return 'expiring';
        }

        return 'valid';
    }
}
