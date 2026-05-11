<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GpsDevice;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GpsDeviceController extends Controller
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
            'status' => ['nullable', 'in:active,inactive'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 10;
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;

        $devices = GpsDevice::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'vehicle:id,name,license_plate',
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('device_name', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('imei', 'like', "%{$search}%")
                        ->orWhere('sim_number', 'like', "%{$search}%")
                        ->orWhere('traccar_device_id', 'like', "%{$search}%");
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('is_active', $status === 'active');
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (GpsDevice $device) => $this->transformDevice($device));

        return response()->json($devices);
    }

    public function show(Request $request, GpsDevice $gpsDevice): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ((int) $gpsDevice->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'message' => 'GPS device not found.',
            ], 404);
        }

        $gpsDevice->load([
            'vehicle:id,name,license_plate',
            'assignmentHistory.vehicle:id,name,license_plate',
            'assignmentHistory.assignedBy:id,name',
        ]);

        return response()->json([
            'data' => $this->transformDevice($gpsDevice, withHistory: true),
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
                'message' => 'You are not allowed to create GPS devices.',
            ], 403);
        }

        $validated = $this->validateDevice($request);

        if (! empty($validated['vehicle_id'])) {
            $vehicleBelongsToTenant = Vehicle::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($validated['vehicle_id'])
                ->exists();

            if (! $vehicleBelongsToTenant) {
                return response()->json([
                    'message' => 'Selected vehicle is invalid.',
                ], 422);
            }
        }

        $device = GpsDevice::create([
            ...$validated,
            'tenant_id' => $tenant->id,
        ]);

        $device->load([
            'vehicle:id,name,license_plate',
        ]);

        return response()->json([
            'message' => 'GPS device created successfully.',
            'data' => $this->transformDevice($device),
        ], 201);
    }

    public function update(Request $request, GpsDevice $gpsDevice): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ((int) $gpsDevice->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'message' => 'GPS device not found.',
            ], 404);
        }

        if (! $user->canManageTenantData()) {
            return response()->json([
                'message' => 'You are not allowed to update GPS devices.',
            ], 403);
        }

        $validated = $this->validateDevice($request, $gpsDevice->id);

        if (! empty($validated['vehicle_id'])) {
            $vehicleBelongsToTenant = Vehicle::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($validated['vehicle_id'])
                ->exists();

            if (! $vehicleBelongsToTenant) {
                return response()->json([
                    'message' => 'Selected vehicle is invalid.',
                ], 422);
            }
        }

        $gpsDevice->update($validated);

        $gpsDevice->load([
            'vehicle:id,name,license_plate',
            'assignmentHistory.vehicle:id,name,license_plate',
            'assignmentHistory.assignedBy:id,name',
        ]);

        return response()->json([
            'message' => 'GPS device updated successfully.',
            'data' => $this->transformDevice($gpsDevice, withHistory: true),
        ]);
    }

    public function destroy(Request $request, GpsDevice $gpsDevice): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ((int) $gpsDevice->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'message' => 'GPS device not found.',
            ], 404);
        }

        if (! $user->canManageTenantData()) {
            return response()->json([
                'message' => 'You are not allowed to delete GPS devices.',
            ], 403);
        }

        $gpsDevice->delete();

        return response()->json([
            'message' => 'GPS device deleted successfully.',
        ]);
    }

    private function validateDevice(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'provider' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'imei' => [
                'required',
                'string',
                'max:255',
                Rule::unique('gps_devices', 'imei')->ignore($ignoreId),
            ],
            'traccar_device_id' => [
                'nullable',
                'integer',
                Rule::unique('gps_devices', 'traccar_device_id')->ignore($ignoreId),
            ],
            'sim_number' => ['nullable', 'string', 'max:255'],
            'capabilities' => ['nullable', 'array'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function transformDevice(GpsDevice $device, bool $withHistory = false): array
    {
        $data = [
            'id' => $device->id,
            'tenant_id' => $device->tenant_id,
            'vehicle_id' => $device->vehicle_id,
            'provider' => $device->provider,
            'device_name' => $device->device_name,
            'model' => $device->model,
            'imei' => $device->imei,
            'traccar_device_id' => $device->traccar_device_id,
            'sim_number' => $device->sim_number,
            'capabilities' => $device->capabilities,
            'is_active' => $device->is_active,
            'last_payload' => $device->last_payload,
            'last_sync_at' => $device->last_sync_at?->toIso8601String(),
            'created_at' => $device->created_at?->toIso8601String(),
            'updated_at' => $device->updated_at?->toIso8601String(),
            'vehicle' => $device->vehicle ? [
                'id' => $device->vehicle->id,
                'name' => $device->vehicle->name,
                'license_plate' => $device->vehicle->license_plate,
            ] : null,
        ];

        if ($withHistory) {
            $data['assignment_history'] = $device->assignmentHistory
                ->sortByDesc('assigned_at')
                ->values()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'assigned_at' => $item->assigned_at?->toIso8601String(),
                        'unassigned_at' => $item->unassigned_at?->toIso8601String(),
                        'notes' => $item->notes,
                        'vehicle' => $item->vehicle ? [
                            'id' => $item->vehicle->id,
                            'name' => $item->vehicle->name,
                            'license_plate' => $item->vehicle->license_plate,
                        ] : null,
                        'assigned_by' => $item->assignedBy ? [
                            'id' => $item->assignedBy->id,
                            'name' => $item->assignedBy->name,
                        ] : null,
                    ];
                });
        }

        return $data;
    }
}
