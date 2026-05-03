<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $onlineThreshold = now()->subMinutes(10);
        $registrationSoonThreshold = now()->addDays(30);

        $vehicles = Vehicle::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'activeGpsDevice:id,vehicle_id,is_active,last_sync_at',
            ])
            ->select([
                'id',
                'tenant_id',
                'name',
                'brand',
                'model',
                'license_plate',
                'current_mileage',
                'registration_expiry_date',
                'last_known_lat',
                'last_known_lng',
                'last_position_at',
                'status',
            ])
            ->get();

        $totalVehicles = $vehicles->count();

        $onlineVehicles = $vehicles
            ->filter(fn (Vehicle $vehicle) => $this->isVehicleOnline($vehicle, $onlineThreshold))
            ->count();

        $offlineVehicles = max($totalVehicles - $onlineVehicles, 0);

        $expiringRegistrationsCount = Vehicle::query()
            ->where('tenant_id', $tenant->id)
            ->whereDate('registration_expiry_date', '>=', today())
            ->whereDate('registration_expiry_date', '<=', $registrationSoonThreshold)
            ->count();

        $expiringRegistrations = Vehicle::query()
            ->where('tenant_id', $tenant->id)
            ->whereDate('registration_expiry_date', '>=', today())
            ->whereDate('registration_expiry_date', '<=', $registrationSoonThreshold)
            ->orderBy('registration_expiry_date')
            ->limit(5)
            ->get([
                'id',
                'name',
                'license_plate',
                'registration_expiry_date',
            ])
            ->map(function (Vehicle $vehicle) {
                $daysLeft = $vehicle->registration_expiry_date
                    ? now()->startOfDay()->diffInDays($vehicle->registration_expiry_date, false)
                    : null;

                return [
                    'id' => $vehicle->id,
                    'name' => $vehicle->name,
                    'license_plate' => $vehicle->license_plate,
                    'registration_expiry_date' => $vehicle->registration_expiry_date?->toDateString(),
                    'days_left' => $daysLeft,
                ];
            })
            ->values();

        $highestMileageVehicles = Vehicle::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'activeGpsDevice:id,vehicle_id,is_active,last_sync_at',
            ])
            ->orderByDesc('current_mileage')
            ->limit(6)
            ->get([
                'id',
                'tenant_id',
                'name',
                'license_plate',
                'current_mileage',
                'status',
            ])
            ->map(function (Vehicle $vehicle) use ($onlineThreshold) {
                return [
                    'id' => $vehicle->id,
                    'name' => $vehicle->name,
                    'license_plate' => $vehicle->license_plate,
                    'current_mileage' => (float) $vehicle->current_mileage,
                    'status' => $vehicle->status,
                    'online' => $this->isVehicleOnline($vehicle, $onlineThreshold),
                ];
            })
            ->values();

        $mapVehicles = $vehicles
            ->filter(fn (Vehicle $vehicle) => $vehicle->last_known_lat !== null && $vehicle->last_known_lng !== null)
            ->map(function (Vehicle $vehicle) use ($onlineThreshold) {
                return [
                    'id' => $vehicle->id,
                    'name' => $vehicle->name,
                    'license_plate' => $vehicle->license_plate,
                    'lat' => (float) $vehicle->last_known_lat,
                    'lng' => (float) $vehicle->last_known_lng,
                    'status' => $vehicle->status,
                    'online' => $this->isVehicleOnline($vehicle, $onlineThreshold),
                    'last_position_at' => $vehicle->last_position_at?->toIso8601String(),
                ];
            })
            ->values();

        $recentActivity = Alert::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('sent_at')
            ->limit(6)
            ->get([
                'id',
                'title',
                'message',
                'severity',
                'sent_at',
            ])
            ->map(function (Alert $alert) {
                return [
                    'id' => $alert->id,
                    'title' => $alert->title,
                    'message' => $alert->message,
                    'severity' => $alert->severity,
                    'sent_at' => $alert->sent_at?->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'overview' => [
                'total_vehicles' => $totalVehicles,
                'online_vehicles' => $onlineVehicles,
                'offline_vehicles' => $offlineVehicles,
                'expiring_registrations_count' => $expiringRegistrationsCount,
                'active_users_count' => User::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('is_active', true)
                    ->count(),
            ],
            'map_vehicles' => $mapVehicles,
            'expiring_registrations' => $expiringRegistrations,
            'highest_mileage_vehicles' => $highestMileageVehicles,
            'recent_activity' => $recentActivity,
        ]);
    }

    private function isVehicleOnline(Vehicle $vehicle, Carbon $onlineThreshold): bool
    {
        $device = $vehicle->activeGpsDevice;

        if (! $device || ! $device->is_active || ! $device->last_sync_at) {
            return false;
        }

        return $device->last_sync_at->gte($onlineThreshold);
    }
}
