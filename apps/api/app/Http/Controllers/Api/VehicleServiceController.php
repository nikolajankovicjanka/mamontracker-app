<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleServiceController extends Controller
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
            'status' => ['nullable', 'in:ok,due_soon,due,no_target'],
            'vehicle_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 10;
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;
        $vehicleId = $validated['vehicle_id'] ?? null;

        $query = VehicleService::query()
            ->join('vehicles', 'vehicle_services.vehicle_id', '=', 'vehicles.id')
            ->where('vehicle_services.tenant_id', $tenant->id)
            ->select('vehicle_services.*')
            ->with([
                'vehicle:id,name,license_plate,current_mileage',
                'creator:id,name',
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('vehicle_services.service_type', 'like', "%{$search}%")
                        ->orWhere('vehicle_services.notes', 'like', "%{$search}%")
                        ->orWhere('vehicles.name', 'like', "%{$search}%")
                        ->orWhere('vehicles.license_plate', 'like', "%{$search}%");
                });
            })
            ->when($vehicleId, fn ($query, $vehicleId) => $query->where('vehicle_services.vehicle_id', $vehicleId))
            ->when($status === 'no_target', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery
                        ->whereNull('vehicle_services.next_service_due_km')
                        ->orWhereNull('vehicles.current_mileage');
                });
            })
            ->when($status === 'due', function ($query) {
                $query
                    ->whereNotNull('vehicle_services.next_service_due_km')
                    ->whereNotNull('vehicles.current_mileage')
                    ->whereColumn('vehicles.current_mileage', '>=', 'vehicle_services.next_service_due_km');
            })
            ->when($status === 'due_soon', function ($query) {
                $query
                    ->whereNotNull('vehicle_services.next_service_due_km')
                    ->whereNotNull('vehicles.current_mileage')
                    ->whereColumn('vehicles.current_mileage', '<', 'vehicle_services.next_service_due_km')
                    ->whereRaw('(vehicle_services.next_service_due_km - vehicles.current_mileage) <= 1000');
            })
            ->when($status === 'ok', function ($query) {
                $query
                    ->whereNotNull('vehicle_services.next_service_due_km')
                    ->whereNotNull('vehicles.current_mileage')
                    ->whereRaw('(vehicle_services.next_service_due_km - vehicles.current_mileage) > 1000');
            })
            ->orderByDesc('vehicle_services.service_date');

        $services = $query
            ->paginate($perPage)
            ->through(fn (VehicleService $service) => $this->transformService($service));

        return response()->json($services);
    }

    public function show(Request $request, VehicleService $service): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ((int) $service->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'message' => 'Service record not found.',
            ], 404);
        }

        $service->load([
            'vehicle:id,name,license_plate,current_mileage',
            'creator:id,name',
        ]);

        return response()->json([
            'data' => $this->transformService($service),
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
                'message' => 'You are not allowed to create services.',
            ], 403);
        }

        $validated = $this->validateService($request, $tenant->id);

        $service = VehicleService::create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
        ]);

        $service->load([
            'vehicle:id,name,license_plate,current_mileage',
            'creator:id,name',
        ]);

        return response()->json([
            'message' => 'Service created successfully.',
            'data' => $this->transformService($service),
        ], 201);
    }

    public function update(Request $request, VehicleService $service): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ((int) $service->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'message' => 'Service record not found.',
            ], 404);
        }

        if (! $user->canManageTenantData()) {
            return response()->json([
                'message' => 'You are not allowed to update services.',
            ], 403);
        }

        $validated = $this->validateService($request, $tenant->id);

        $service->update($validated);

        $service->load([
            'vehicle:id,name,license_plate,current_mileage',
            'creator:id,name',
        ]);

        return response()->json([
            'message' => 'Service updated successfully.',
            'data' => $this->transformService($service),
        ]);
    }

    public function destroy(Request $request, VehicleService $service): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ((int) $service->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'message' => 'Service record not found.',
            ], 404);
        }

        if (! $user->canManageTenantData()) {
            return response()->json([
                'message' => 'You are not allowed to delete services.',
            ], 403);
        }

        $service->delete();

        return response()->json([
            'message' => 'Service deleted successfully.',
        ]);
    }

    private function validateService(Request $request, int $tenantId): array
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'service_type' => ['required', 'string', 'max:255'],
            'service_date' => ['required', 'date'],
            'mileage_at_service' => ['required', 'numeric', 'min:0'],
            'next_service_due_km' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $vehicleBelongsToTenant = Vehicle::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($validated['vehicle_id'])
            ->exists();

        if (! $vehicleBelongsToTenant) {
            abort(response()->json([
                'message' => 'Selected vehicle is invalid.',
            ], 422));
        }

        return $validated;
    }

    private function transformService(VehicleService $service): array
    {
        $currentMileage = $service->vehicle?->current_mileage !== null
            ? (float) $service->vehicle->current_mileage
            : null;

        $mileageUntilDue = null;

        if ($service->next_service_due_km !== null && $currentMileage !== null) {
            $mileageUntilDue = (float) $service->next_service_due_km - $currentMileage;
        }

        return [
            'id' => $service->id,
            'tenant_id' => $service->tenant_id,
            'vehicle_id' => $service->vehicle_id,
            'created_by' => $service->created_by,
            'service_type' => $service->service_type,
            'service_date' => $service->service_date?->toDateString(),
            'mileage_at_service' => $service->mileage_at_service !== null ? (float) $service->mileage_at_service : null,
            'next_service_due_km' => $service->next_service_due_km !== null ? (float) $service->next_service_due_km : null,
            'notes' => $service->notes,
            'service_status' => $this->resolveServiceStatus($service, $currentMileage),
            'mileage_until_due' => $mileageUntilDue,
            'vehicle' => $service->vehicle ? [
                'id' => $service->vehicle->id,
                'name' => $service->vehicle->name,
                'license_plate' => $service->vehicle->license_plate,
                'current_mileage' => $service->vehicle->current_mileage !== null
                    ? (float) $service->vehicle->current_mileage
                    : null,
            ] : null,
            'creator' => $service->creator ? [
                'id' => $service->creator->id,
                'name' => $service->creator->name,
            ] : null,
            'created_at' => $service->created_at?->toIso8601String(),
            'updated_at' => $service->updated_at?->toIso8601String(),
        ];
    }

    private function resolveServiceStatus(VehicleService $service, ?float $currentMileage): string
    {
        if ($service->next_service_due_km === null || $currentMileage === null) {
            return 'no_target';
        }

        if ($service->isDue($currentMileage)) {
            return 'due';
        }

        if ($service->isDueSoon($currentMileage)) {
            return 'due_soon';
        }

        return 'ok';
    }
}
