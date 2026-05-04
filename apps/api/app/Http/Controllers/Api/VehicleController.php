<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
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
            'status' => ['nullable', 'in:active,inactive,maintenance'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 10;
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;

        $vehicles = Vehicle::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'activeGpsDevice:id,vehicle_id,device_name,is_active,last_sync_at',
            ])
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
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (Vehicle $vehicle) => $this->transformVehicle($vehicle));

        return response()->json($vehicles);
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
                'message' => 'Vehicle not found.',
            ], 404);
        }

        $vehicle->load([
            'activeGpsDevice:id,vehicle_id,device_name,is_active,last_sync_at,imei,traccar_device_id,model,provider',
        ]);

        return response()->json([
            'data' => $this->transformVehicle($vehicle),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->canManageTenantData()) {
            return response()->json([
                'message' => 'You are not allowed to create vehicles.',
            ], 403);
        }

        $validated = $this->validateVehicle($request);

        $vehicle = Vehicle::create([
            ...$validated,
            'tenant_id' => $tenant->id,
        ]);

        $vehicle->load([
            'activeGpsDevice:id,vehicle_id,device_name,is_active,last_sync_at',
        ]);

        return response()->json([
            'message' => 'Vehicle created successfully.',
            'data' => $this->transformVehicle($vehicle),
        ], 201);
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
                'message' => 'Vehicle not found.',
            ], 404);
        }

        if (! $user->canManageTenantData()) {
            return response()->json([
                'message' => 'You are not allowed to update vehicles.',
            ], 403);
        }

        $validated = $this->validateVehicle($request, $vehicle->id);

        $vehicle->update($validated);

        $vehicle->load([
            'activeGpsDevice:id,vehicle_id,device_name,is_active,last_sync_at',
        ]);

        return response()->json([
            'message' => 'Vehicle updated successfully.',
            'data' => $this->transformVehicle($vehicle),
        ]);
    }

    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
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
                'message' => 'Vehicle not found.',
            ], 404);
        }

        if (! $user->canManageTenantData()) {
            return response()->json([
                'message' => 'You are not allowed to delete vehicles.',
            ], 403);
        }

        $vehicle->load('activeGpsDevice');

        if ($vehicle->activeGpsDevice) {
            return response()->json([
                'message' => 'Vehicle cannot be deleted while a GPS device is assigned to it.',
            ], 422);
        }

        $vehicle->delete();

        return response()->json([
            'message' => 'Vehicle deleted successfully.',
        ]);
    }

    private function validateVehicle(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'production_year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'license_plate' => ['required', 'string', 'max:255'],
            'vin' => ['nullable', 'string', 'max:255'],
            'registration_expiry_date' => ['nullable', 'date'],
            'current_mileage' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive,maintenance'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function transformVehicle(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'name' => $vehicle->name,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'production_year' => $vehicle->production_year,
            'license_plate' => $vehicle->license_plate,
            'vin' => $vehicle->vin,
            'registration_expiry_date' => $vehicle->registration_expiry_date?->toDateString(),
            'current_mileage' => (float) $vehicle->current_mileage,
            'status' => $vehicle->status,
            'notes' => $vehicle->notes,
            'last_known_lat' => $vehicle->last_known_lat !== null ? (float) $vehicle->last_known_lat : null,
            'last_known_lng' => $vehicle->last_known_lng !== null ? (float) $vehicle->last_known_lng : null,
            'last_position_at' => $vehicle->last_position_at?->toIso8601String(),
            'last_speed_kph' => $vehicle->last_speed_kph !== null ? (float) $vehicle->last_speed_kph : null,
            'gps_device' => $vehicle->activeGpsDevice ? [
                'device_name' => $vehicle->activeGpsDevice->device_name,
                'model' => $vehicle->activeGpsDevice->model ?? null,
                'provider' => $vehicle->activeGpsDevice->provider ?? null,
                'imei' => $vehicle->activeGpsDevice->imei ?? null,
                'traccar_device_id' => $vehicle->activeGpsDevice->traccar_device_id ?? null,
                'is_active' => $vehicle->activeGpsDevice->is_active,
                'last_sync_at' => $vehicle->activeGpsDevice->last_sync_at?->toIso8601String(),
            ] : null,
        ];
    }
}
