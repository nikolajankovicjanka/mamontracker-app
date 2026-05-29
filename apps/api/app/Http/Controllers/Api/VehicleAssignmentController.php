<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = current_tenant();
        $authUser = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $authUser || ! $authUser->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $this->canManageAssignments($authUser)) {
            return response()->json([
                'message' => 'You are not allowed to view vehicle assignments.',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'vehicle_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,ended,cancelled'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 10;
        $userId = $validated['user_id'] ?? null;
        $vehicleId = $validated['vehicle_id'] ?? null;
        $status = $validated['status'] ?? null;

        $assignments = VehicleAssignment::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'user:id,name,email,role,is_active',
                'vehicle:id,name,license_plate,status,current_mileage',
                'assignedBy:id,name',
            ])
            ->when($userId, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($vehicleId, fn ($query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->latest('assigned_from')
            ->paginate($perPage)
            ->through(fn (VehicleAssignment $assignment) => $this->transformAssignment($assignment));

        return response()->json($assignments);
    }

    public function show(Request $request, VehicleAssignment $vehicleAssignment): JsonResponse
    {
        $tenant = current_tenant();
        $authUser = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $authUser || ! $authUser->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $this->canManageAssignments($authUser)) {
            return response()->json([
                'message' => 'You are not allowed to view vehicle assignments.',
            ], 403);
        }

        if ((int) $vehicleAssignment->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'message' => 'Vehicle assignment not found.',
            ], 404);
        }

        $vehicleAssignment->load([
            'user:id,name,email,role,is_active',
            'vehicle:id,name,license_plate,status,current_mileage',
            'assignedBy:id,name',
        ]);

        return response()->json([
            'data' => $this->transformAssignment($vehicleAssignment),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = current_tenant();
        $authUser = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $authUser || ! $authUser->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $this->canManageAssignments($authUser)) {
            return response()->json([
                'message' => 'You are not allowed to create vehicle assignments.',
            ], 403);
        }

        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'assignment_type' => ['required', 'in:primary,secondary,temporary'],
            'assigned_from' => ['required', 'date'],
            'assigned_until' => ['nullable', 'date', 'after_or_equal:assigned_from'],
            'notes' => ['nullable', 'string'],
            'start_mileage' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($validated['user_id'])
            ->first();

        $vehicle = Vehicle::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($validated['vehicle_id'])
            ->first();

        if (! $user || ! in_array($user->role, ['tenant_admin', 'tenant_user'], true)) {
            return response()->json([
                'message' => 'Selected user is invalid.',
            ], 422);
        }

        if (! $vehicle) {
            return response()->json([
                'message' => 'Selected vehicle is invalid.',
            ], 422);
        }

        $duplicateActiveAssignment = VehicleAssignment::query()
            ->where('tenant_id', $tenant->id)
            ->where('vehicle_id', $validated['vehicle_id'])
            ->where('user_id', $validated['user_id'])
            ->where('status', 'active')
            ->whereNull('unassigned_at')
            ->exists();

        if ($duplicateActiveAssignment) {
            return response()->json([
                'message' => 'This user already has an active assignment for the selected vehicle.',
            ], 422);
        }

        $assignment = VehicleAssignment::create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $validated['vehicle_id'],
            'user_id' => $validated['user_id'],
            'assigned_by' => $authUser->id,
            'assignment_type' => $validated['assignment_type'],
            'assigned_from' => $validated['assigned_from'],
            'assigned_until' => $validated['assigned_until'] ?? null,
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
            'start_mileage' => $validated['start_mileage'] ?? null,
        ]);

        $assignment->load([
            'user:id,name,email,role,is_active',
            'vehicle:id,name,license_plate,status,current_mileage',
            'assignedBy:id,name',
        ]);

        return response()->json([
            'message' => 'Vehicle assignment created successfully.',
            'data' => $this->transformAssignment($assignment),
        ], 201);
    }

    public function update(Request $request, VehicleAssignment $vehicleAssignment): JsonResponse
    {
        $tenant = current_tenant();
        $authUser = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $authUser || ! $authUser->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $this->canManageAssignments($authUser)) {
            return response()->json([
                'message' => 'You are not allowed to update vehicle assignments.',
            ], 403);
        }

        if ((int) $vehicleAssignment->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'message' => 'Vehicle assignment not found.',
            ], 404);
        }

        $validated = $request->validate([
            'assignment_type' => ['required', 'in:primary,secondary,temporary'],
            'assigned_from' => ['required', 'date'],
            'assigned_until' => ['nullable', 'date', 'after_or_equal:assigned_from'],
            'notes' => ['nullable', 'string'],
            'start_mileage' => ['nullable', 'numeric', 'min:0'],
            'end_mileage' => ['nullable', 'numeric', 'min:0'],
        ]);

        $vehicleAssignment->update([
            'assignment_type' => $validated['assignment_type'],
            'assigned_from' => $validated['assigned_from'],
            'assigned_until' => $validated['assigned_until'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'start_mileage' => $validated['start_mileage'] ?? null,
            'end_mileage' => $validated['end_mileage'] ?? null,
        ]);

        $vehicleAssignment->load([
            'user:id,name,email,role,is_active',
            'vehicle:id,name,license_plate,status,current_mileage',
            'assignedBy:id,name',
        ]);

        return response()->json([
            'message' => 'Vehicle assignment updated successfully.',
            'data' => $this->transformAssignment($vehicleAssignment),
        ]);
    }

    public function end(Request $request, VehicleAssignment $vehicleAssignment): JsonResponse
    {
        $tenant = current_tenant();
        $authUser = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $authUser || ! $authUser->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $this->canManageAssignments($authUser)) {
            return response()->json([
                'message' => 'You are not allowed to end vehicle assignments.',
            ], 403);
        }

        if ((int) $vehicleAssignment->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'message' => 'Vehicle assignment not found.',
            ], 404);
        }

        if ($vehicleAssignment->status !== 'active' || $vehicleAssignment->unassigned_at !== null) {
            return response()->json([
                'message' => 'Only active assignments can be ended.',
            ], 422);
        }

        $validated = $request->validate([
            'unassigned_at' => ['nullable', 'date'],
            'end_mileage' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $vehicleAssignment->update([
            'status' => 'ended',
            'unassigned_at' => $validated['unassigned_at'] ?? now(),
            'end_mileage' => $validated['end_mileage'] ?? $vehicleAssignment->end_mileage,
            'notes' => $validated['notes'] ?? $vehicleAssignment->notes,
        ]);

        $vehicleAssignment->load([
            'user:id,name,email,role,is_active',
            'vehicle:id,name,license_plate,status,current_mileage',
            'assignedBy:id,name',
        ]);

        return response()->json([
            'message' => 'Vehicle assignment ended successfully.',
            'data' => $this->transformAssignment($vehicleAssignment),
        ]);
    }

    private function canManageAssignments(User $authUser): bool
    {
        return $authUser->canManageUsers()
            || $authUser->canAssignVehicles();
    }

    private function transformAssignment(VehicleAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'tenant_id' => $assignment->tenant_id,
            'vehicle_id' => $assignment->vehicle_id,
            'user_id' => $assignment->user_id,
            'assigned_by' => $assignment->assigned_by,
            'assignment_type' => $assignment->assignment_type,
            'assigned_from' => $assignment->assigned_from?->toIso8601String(),
            'assigned_until' => $assignment->assigned_until?->toIso8601String(),
            'unassigned_at' => $assignment->unassigned_at?->toIso8601String(),
            'status' => $assignment->status,
            'notes' => $assignment->notes,
            'start_mileage' => $assignment->start_mileage !== null ? (float) $assignment->start_mileage : null,
            'end_mileage' => $assignment->end_mileage !== null ? (float) $assignment->end_mileage : null,
            'user' => $assignment->user ? [
                'id' => $assignment->user->id,
                'name' => $assignment->user->name,
                'email' => $assignment->user->email,
                'role' => $assignment->user->role,
                'is_active' => (bool) $assignment->user->is_active,
            ] : null,
            'vehicle' => $assignment->vehicle ? [
                'id' => $assignment->vehicle->id,
                'name' => $assignment->vehicle->name,
                'license_plate' => $assignment->vehicle->license_plate,
                'status' => $assignment->vehicle->status,
                'current_mileage' => $assignment->vehicle->current_mileage !== null ? (float) $assignment->vehicle->current_mileage : null,
            ] : null,
            'assigned_by_user' => $assignment->assignedBy ? [
                'id' => $assignment->assignedBy->id,
                'name' => $assignment->assignedBy->name,
            ] : null,
            'created_at' => $assignment->created_at?->toIso8601String(),
            'updated_at' => $assignment->updated_at?->toIso8601String(),
        ];
    }
}
