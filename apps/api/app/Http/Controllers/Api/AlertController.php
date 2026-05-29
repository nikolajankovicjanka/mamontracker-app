<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\AlertUserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'filter' => ['nullable', 'in:all,unread'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $filter = $validated['filter'] ?? 'all';
        $perPage = $validated['per_page'] ?? 10;

        $query = Alert::query()
            ->where('alerts.tenant_id', $tenant->id)
            ->leftJoin('alert_user_statuses as user_status', function ($join) use ($user) {
                $join->on('alerts.id', '=', 'user_status.alert_id')
                    ->where('user_status.user_id', '=', $user->id);
            })
            ->select([
                'alerts.*',
                DB::raw('COALESCE(user_status.is_read, 0) as user_is_read'),
                'user_status.read_at as user_read_at',
                'user_status.seen_at as user_seen_at',
            ])
            ->with([
                'vehicle:id,name,license_plate',
                'gpsDevice:id,device_name',
            ])
            ->when($filter === 'unread', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery
                        ->whereNull('user_status.id')
                        ->orWhere('user_status.is_read', false);
                });
            })
            ->orderByRaw('COALESCE(alerts.sent_at, alerts.created_at) desc');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'current_page' => $paginator->currentPage(),
            'data' => collect($paginator->items())
                ->map(fn (Alert $alert) => $this->transformAlert($alert))
                ->values(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'unread_count' => $this->unreadAlertsCount($tenant->id, $user->id),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'unread_count' => $this->unreadAlertsCount($tenant->id, $user->id),
        ]);
    }

    public function markAsRead(Request $request, Alert $alert): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ((int) $alert->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'message' => 'Alert not found.',
            ], 404);
        }

        AlertUserStatus::updateOrCreate(
            [
                'alert_id' => $alert->id,
                'user_id' => $user->id,
            ],
            [
                'is_read' => true,
                'seen_at' => now(),
                'read_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Alert marked as read.',
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $alertIds = Alert::query()
            ->where('tenant_id', $tenant->id)
            ->pluck('id');

        if ($alertIds->isEmpty()) {
            return response()->json([
                'message' => 'All alerts marked as read.',
            ]);
        }

        $existingStatuses = AlertUserStatus::query()
            ->where('user_id', $user->id)
            ->whereIn('alert_id', $alertIds)
            ->get()
            ->keyBy('alert_id');

        foreach ($alertIds as $alertId) {
            $existing = $existingStatuses->get($alertId);

            if ($existing) {
                if (! $existing->is_read) {
                    $existing->update([
                        'is_read' => true,
                        'seen_at' => $existing->seen_at ?? now(),
                        'read_at' => now(),
                    ]);
                }

                continue;
            }

            AlertUserStatus::create([
                'alert_id' => $alertId,
                'user_id' => $user->id,
                'is_read' => true,
                'seen_at' => now(),
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'All alerts marked as read.',
        ]);
    }

    private function unreadAlertsCount(int $tenantId, int $userId): int
    {
        return Alert::query()
            ->where('alerts.tenant_id', $tenantId)
            ->leftJoin('alert_user_statuses as user_status', function ($join) use ($userId) {
                $join->on('alerts.id', '=', 'user_status.alert_id')
                    ->where('user_status.user_id', '=', $userId);
            })
            ->where(function ($query) {
                $query
                    ->whereNull('user_status.id')
                    ->orWhere('user_status.is_read', false);
            })
            ->count();
    }

    private function transformAlert(Alert $alert): array
    {
        $meta = is_array($alert->meta) ? $alert->meta : [];

        return [
            'id' => $alert->id,
            'type' => $alert->type,
            'severity' => $alert->severity,
            'title' => $alert->title,
            'message' => $alert->message,
            'is_read' => (bool) ($alert->user_is_read ?? false),
            'read_at' => $alert->user_read_at,
            'seen_at' => $alert->user_seen_at,
            'sent_at' => $alert->sent_at?->toIso8601String(),
            'vehicle' => $alert->vehicle ? [
                'id' => $alert->vehicle->id,
                'name' => $alert->vehicle->name,
                'license_plate' => $alert->vehicle->license_plate,
            ] : null,
            'gps_device' => $alert->gpsDevice ? [
                'id' => $alert->gpsDevice->id,
                'device_name' => $alert->gpsDevice->device_name,
            ] : null,
            'route_name' => $meta['route_name'] ?? null,
            'route_params' => $meta['route_params'] ?? [],
            'meta' => $meta,
        ];
    }
}
