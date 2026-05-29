<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
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

        if (! $authUser->canManageUsers()) {
            return response()->json([
                'message' => 'You are not allowed to view users.',
            ], 403);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'in:tenant_admin,tenant_user'],
            'status' => ['nullable', 'in:active,inactive'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 10;
        $search = $validated['search'] ?? null;
        $role = $validated['role'] ?? null;
        $status = $validated['status'] ?? null;

        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('role', ['tenant_admin', 'tenant_user'])
            ->withCount([
                'vehicleAssignments',
                'activeVehicleAssignments',
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role, fn ($query, $role) => $query->where('role', $role))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (User $user) => $this->transformUser($user));

        return response()->json($users);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $tenant = current_tenant();
        $authUser = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $authUser || ! $authUser->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $authUser->canManageUsers()) {
            return response()->json([
                'message' => 'You are not allowed to view users.',
            ], 403);
        }

        if ((int) $user->tenant_id !== (int) $tenant->id || ! in_array($user->role, ['tenant_admin', 'tenant_user'], true)) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        $user->load([
            'tenant:id,name,slug,plan',
            'activeVehicleAssignments' => fn ($query) => $query
                ->with([
                    'vehicle:id,name,license_plate,status,current_mileage',
                    'assignedBy:id,name',
                ])
                ->latest('assigned_from'),
            'vehicleAssignments' => fn ($query) => $query
                ->with([
                    'vehicle:id,name,license_plate,status,current_mileage',
                    'assignedBy:id,name',
                ])
                ->latest('assigned_from'),
        ]);

        return response()->json([
            'data' => $this->transformUser($user, true),
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

        if (! $authUser->canManageUsers()) {
            return response()->json([
                'message' => 'You are not allowed to create users.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:tenant_admin,tenant_user'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'permissions' => $this->resolvePermissions(
                role: $validated['role'],
                inputPermissions: $validated['permissions'] ?? null,
            ),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'data' => $this->transformUser($user->fresh()),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $tenant = current_tenant();
        $authUser = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $authUser || ! $authUser->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $authUser->canManageUsers()) {
            return response()->json([
                'message' => 'You are not allowed to update users.',
            ], 403);
        }

        if ((int) $user->tenant_id !== (int) $tenant->id || ! in_array($user->role, ['tenant_admin', 'tenant_user'], true)) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'in:tenant_admin,tenant_user'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['nullable', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'] ?: $user->password,
            'role' => $validated['role'],
            'permissions' => $this->resolvePermissions(
                role: $validated['role'],
                inputPermissions: $validated['permissions'] ?? null,
                existingPermissions: $user->permissions,
            ),
            'is_active' => $validated['is_active'],
        ]);

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $this->transformUser($user->fresh()),
        ]);
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $tenant = current_tenant();
        $authUser = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $authUser || ! $authUser->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $authUser->canManageUsers()) {
            return response()->json([
                'message' => 'You are not allowed to update users.',
            ], 403);
        }

        if ((int) $user->tenant_id !== (int) $tenant->id || ! in_array($user->role, ['tenant_admin', 'tenant_user'], true)) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        if ((int) $authUser->id === (int) $user->id && $validated['is_active'] === false) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
            ], 422);
        }

        $user->update([
            'is_active' => $validated['is_active'],
        ]);

        return response()->json([
            'message' => 'User status updated successfully.',
            'data' => $this->transformUser($user->fresh()),
        ]);
    }

    private function transformUser(User $user, bool $withDetails = false): array
    {
        $data = [
            'id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'permissions' => $this->resolvePermissions(
                role: $user->role,
                inputPermissions: $user->permissions,
            ),
            'is_active' => (bool) $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'vehicle_assignments_count' => $user->vehicle_assignments_count ?? null,
            'active_vehicle_assignments_count' => $user->active_vehicle_assignments_count ?? null,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];

        if (! $withDetails) {
            return $data;
        }

        $data['tenant'] = $user->relationLoaded('tenant') && $user->tenant
            ? [
                'id' => $user->tenant->id,
                'name' => $user->tenant->name,
                'slug' => $user->tenant->slug,
                'plan' => $user->tenant->plan,
            ]
            : null;

        $data['active_assignments'] = $user->relationLoaded('activeVehicleAssignments')
            ? $user->activeVehicleAssignments->map(fn ($assignment) => $this->transformAssignment($assignment))->values()
            : [];

        $data['assignment_history'] = $user->relationLoaded('vehicleAssignments')
            ? $user->vehicleAssignments->map(fn ($assignment) => $this->transformAssignment($assignment))->values()
            : [];

        return $data;
    }

    private function transformAssignment($assignment): array
    {
        return [
            'id' => $assignment->id,
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
        ];
    }

    private function resolvePermissions(
        string $role,
        ?array $inputPermissions = null,
        ?array $existingPermissions = null,
    ): array {
        $permissionKeys = $this->permissionKeys();

        if ($role === 'tenant_admin') {
            return collect($permissionKeys)
                ->mapWithKeys(fn ($key) => [$key => true])
                ->all();
        }

        $base = collect($permissionKeys)
            ->mapWithKeys(fn ($key) => [$key => false])
            ->all();

        if (is_array($existingPermissions)) {
            foreach ($permissionKeys as $key) {
                if (array_key_exists($key, $existingPermissions)) {
                    $base[$key] = (bool) $existingPermissions[$key];
                }
            }
        }

        if (is_array($inputPermissions)) {
            foreach ($permissionKeys as $key) {
                if (array_key_exists($key, $inputPermissions)) {
                    $base[$key] = (bool) $inputPermissions[$key];
                }
            }
        }

        return $base;
    }

    private function permissionKeys(): array
    {
        return [
            'can_manage_users',
            'can_assign_vehicles',
            'can_manage_services',
            'can_manage_registrations',
            'can_manage_gps_devices',
            'can_log_fuel',
            'can_view_reports',
            'can_view_alerts',
        ];
    }
}
