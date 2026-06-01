<?php

namespace App\Http\Controllers\Api;

use App\Exports\Reports\ReportRowsExport;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportsController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        [$tenant, $user] = $this->resolveTenantContext($request);

        $vehiclesBase = Vehicle::query()->where('tenant_id', $tenant->id);
        $services = VehicleService::query()
            ->where('tenant_id', $tenant->id)
            ->with('vehicle:id,name,license_plate,current_mileage')
            ->get();

        $registrations = Vehicle::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('registration_expiry_date')
            ->get();

        $dueSoonServices = $services->filter(function (VehicleService $service) {
            if (! $service->vehicle || $service->vehicle->current_mileage === null) {
                return false;
            }

            return $service->isDueSoon((float) $service->vehicle->current_mileage);
        });

        $dueServices = $services->filter(function (VehicleService $service) {
            if (! $service->vehicle || $service->vehicle->current_mileage === null) {
                return false;
            }

            return $service->isDue((float) $service->vehicle->current_mileage);
        });

        $upcomingRegistrations = $registrations
            ->filter(function (Vehicle $vehicle) {
                if ($vehicle->registration_expiry_date === null) {
                    return false;
                }

                $daysLeft = now()->startOfDay()->diffInDays($vehicle->registration_expiry_date, false);

                return $daysLeft >= 0 && $daysLeft <= 7;
            });

        $expiredRegistrations = $registrations
            ->filter(fn (Vehicle $vehicle) => $vehicle->hasExpiredRegistration());

        $unreadAlertsCount = Alert::query()
            ->where('alerts.tenant_id', $tenant->id)
            ->leftJoin('alert_user_statuses as user_status', function ($join) use ($user) {
                $join->on('alerts.id', '=', 'user_status.alert_id')
                    ->where('user_status.user_id', '=', $user->id);
            })
            ->where(function ($query) {
                $query
                    ->whereNull('user_status.id')
                    ->orWhere('user_status.is_read', false);
            })
            ->count();

        $topMileageVehicles = Vehicle::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('current_mileage')
            ->limit(5)
            ->get()
            ->map(fn (Vehicle $vehicle) => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'license_plate' => $vehicle->license_plate,
                'current_mileage' => $vehicle->current_mileage !== null ? (float) $vehicle->current_mileage : null,
            ])
            ->values();

        $topAssignedUsers = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('role', ['tenant_admin', 'tenant_user'])
            ->withCount('activeVehicleAssignments')
            ->orderByDesc('active_vehicle_assignments_count')
            ->limit(5)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'active_vehicle_assignments_count' => $user->active_vehicle_assignments_count,
            ])
            ->values();

        $upcomingRegistrationsList = $upcomingRegistrations
            ->sortBy('registration_expiry_date')
            ->take(5)
            ->map(fn (Vehicle $vehicle) => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'license_plate' => $vehicle->license_plate,
                'registration_expiry_date' => $vehicle->registration_expiry_date?->toDateString(),
            ])
            ->values();

        $dueServicesList = $dueServices
            ->take(5)
            ->map(function (VehicleService $service) {
                $currentMileage = $service->vehicle?->current_mileage !== null
                    ? (float) $service->vehicle->current_mileage
                    : null;

                $mileageUntilDue = null;

                if ($service->next_service_due_km !== null && $currentMileage !== null) {
                    $mileageUntilDue = (float) $service->next_service_due_km - $currentMileage;
                }

                return [
                    'id' => $service->id,
                    'service_type' => $service->service_type,
                    'vehicle_name' => $service->vehicle?->name,
                    'license_plate' => $service->vehicle?->license_plate,
                    'mileage_until_due' => $mileageUntilDue,
                ];
            })
            ->values();

        return response()->json([
            'metrics' => [
                ['key' => 'total_vehicles', 'label' => 'Ukupno vozila', 'value' => $vehiclesBase->count()],
                ['key' => 'active_vehicles', 'label' => 'Aktivna vozila', 'value' => (clone $vehiclesBase)->where('status', 'active')->count()],
                ['key' => 'maintenance_vehicles', 'label' => 'Na održavanju', 'value' => (clone $vehiclesBase)->where('status', 'maintenance')->count()],
                ['key' => 'vehicles_without_gps', 'label' => 'Bez GPS uređaja', 'value' => (clone $vehiclesBase)->whereDoesntHave('activeGpsDevice')->count()],
                ['key' => 'vehicles_without_assignments', 'label' => 'Bez zaduženja', 'value' => (clone $vehiclesBase)->whereDoesntHave('activeAssignments')->count()],
                ['key' => 'registrations_expiring', 'label' => 'Registracije uskoro ističu', 'value' => $upcomingRegistrations->count()],
                ['key' => 'registrations_expired', 'label' => 'Istekle registracije', 'value' => $expiredRegistrations->count()],
                ['key' => 'services_due_soon', 'label' => 'Servisi uskoro', 'value' => $dueSoonServices->count()],
                ['key' => 'services_due', 'label' => 'Dospjeli servisi', 'value' => $dueServices->count()],
                ['key' => 'active_users', 'label' => 'Aktivni korisnici', 'value' => User::query()->where('tenant_id', $tenant->id)->where('is_active', true)->whereIn('role', ['tenant_admin', 'tenant_user'])->count()],
                ['key' => 'unread_alerts', 'label' => 'Nepročitani alerti', 'value' => $unreadAlertsCount],
            ],
            'highlights' => [
                'top_mileage_vehicles' => $topMileageVehicles,
                'upcoming_registrations' => $upcomingRegistrationsList,
                'due_services' => $dueServicesList,
                'top_assigned_users' => $topAssignedUsers,
            ],
        ]);
    }

    public function dataset(Request $request, string $report): JsonResponse
    {
        [$tenant, $user] = $this->resolveTenantContext($request);

        $this->validateFilters($request);

        $data = $this->buildReportData($report, $tenant, $user, $request);

        return response()->json($data);
    }

    public function exportExcel(Request $request, string $report): BinaryFileResponse
    {
        [$tenant, $user] = $this->resolveTenantContext($request);

        $this->validateFilters($request);

        $data = $this->buildReportData($report, $tenant, $user, $request);
        $filename = Str::slug($data['title']) . '-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(
            new ReportRowsExport($data['columns'], $data['rows']),
            $filename
        );
    }

    public function exportPdf(Request $request, string $report)
    {
        [$tenant, $user] = $this->resolveTenantContext($request);

        $this->validateFilters($request);

        $data = $this->buildReportData($report, $tenant, $user, $request);
        $filename = Str::slug($data['title']) . '-' . now()->format('Ymd-His') . '.pdf';

        $pdf = Pdf::loadView('reports.table', [
            'title' => $data['title'],
            'tenantName' => $tenant->name,
            'columns' => $data['columns'],
            'rows' => $data['rows'],
            'generatedAt' => now()->format('d.m.Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    private function resolveTenantContext(Request $request): array
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            abort(response()->json([
                'message' => 'Unauthenticated.',
            ], 401));
        }

        return [$tenant, $user];
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:50'],
            'severity' => ['nullable', 'string', 'max:50'],
            'assignment' => ['nullable', 'string', 'max:50'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);
    }

    private function buildReportData(string $report, Tenant $tenant, User $user, Request $request): array
    {
        return match ($report) {
            'fleet' => $this->fleetReport($tenant, $request),
            'registrations' => $this->registrationsReport($tenant, $request),
            'services' => $this->servicesReport($tenant, $request),
            'assignments' => $this->assignmentsReport($tenant, $request),
            'users' => $this->usersReport($tenant, $request),
            'alerts' => $this->alertsReport($tenant, $user, $request),
            default => abort(response()->json([
                'message' => 'Report not found.',
            ], 404)),
        };
    }

    private function fleetReport(Tenant $tenant, Request $request): array
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $assignment = $request->string('assignment')->toString();

        $vehicles = Vehicle::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'activeGpsDevice:id,vehicle_id,device_name,is_active,last_sync_at',
                'activeAssignments' => fn ($query) => $query->with('user:id,name'),
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
            ->when(in_array($status, ['active', 'inactive', 'maintenance'], true), fn ($query) => $query->where('status', $status))
            ->when($assignment === 'assigned', fn ($query) => $query->whereHas('activeAssignments'))
            ->when($assignment === 'unassigned', fn ($query) => $query->whereDoesntHave('activeAssignments'))
            ->orderBy('name')
            ->get();

        return [
            'title' => 'Fleet report',
            'columns' => [
                ['key' => 'name', 'label' => 'Vozilo'],
                ['key' => 'brand_model', 'label' => 'Marka / model'],
                ['key' => 'license_plate', 'label' => 'Tablice'],
                ['key' => 'current_mileage', 'label' => 'Kilometraža'],
                ['key' => 'registration_expiry_date', 'label' => 'Registracija'],
                ['key' => 'gps_device', 'label' => 'GPS uređaj'],
                ['key' => 'active_assignees', 'label' => 'Zaduženi korisnici'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $vehicles->map(function (Vehicle $vehicle) {
                return [
                    'name' => $vehicle->name,
                    'brand_model' => trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')),
                    'license_plate' => $vehicle->license_plate ?: '—',
                    'current_mileage' => $vehicle->current_mileage !== null ? number_format((float) $vehicle->current_mileage, 0, ',', '.') . ' km' : '—',
                    'registration_expiry_date' => $vehicle->registration_expiry_date?->format('d.m.Y') ?? '—',
                    'gps_device' => $vehicle->activeGpsDevice?->device_name ?? '—',
                    'active_assignees' => $vehicle->activeAssignments->pluck('user.name')->filter()->join(', ') ?: '—',
                    'status' => $vehicle->status,
                ];
            })->values()->all(),
        ];
    }

    private function registrationsReport(Tenant $tenant, Request $request): array
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $days = $request->integer('days', 7);

        $vehicles = Vehicle::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('registration_expiry_date')
            ->when($search, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('license_plate', 'like', "%{$search}%");
                });
            })
            ->orderBy('registration_expiry_date')
            ->get()
            ->map(function (Vehicle $vehicle) use ($days) {
                $daysLeft = now()->startOfDay()->diffInDays($vehicle->registration_expiry_date, false);

                $state = 'valid';

                if ($daysLeft < 0) {
                    $state = 'expired';
                } elseif ($daysLeft <= $days) {
                    $state = 'expiring';
                }

                return [
                    'vehicle' => $vehicle,
                    'days_left' => $daysLeft,
                    'state' => $state,
                ];
            })
            ->filter(function (array $item) use ($status) {
                if (! in_array($status, ['expired', 'expiring', 'valid'], true)) {
                    return true;
                }

                return $item['state'] === $status;
            })
            ->values();

        return [
            'title' => 'Registrations report',
            'columns' => [
                ['key' => 'name', 'label' => 'Vozilo'],
                ['key' => 'license_plate', 'label' => 'Tablice'],
                ['key' => 'registration_expiry_date', 'label' => 'Datum isteka'],
                ['key' => 'days_left', 'label' => 'Dana do isteka'],
                ['key' => 'state', 'label' => 'Stanje'],
            ],
            'rows' => $vehicles->map(function (array $item) {
                /** @var Vehicle $vehicle */
                $vehicle = $item['vehicle'];

                return [
                    'name' => $vehicle->name,
                    'license_plate' => $vehicle->license_plate ?: '—',
                    'registration_expiry_date' => $vehicle->registration_expiry_date?->format('d.m.Y') ?? '—',
                    'days_left' => $item['days_left'],
                    'state' => $item['state'],
                ];
            })->all(),
        ];
    }

    private function servicesReport(Tenant $tenant, Request $request): array
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $services = VehicleService::query()
            ->where('tenant_id', $tenant->id)
            ->with('vehicle:id,name,license_plate,current_mileage')
            ->when($search, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('service_type', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->latest('service_date')
            ->get()
            ->map(function (VehicleService $service) {
                $currentMileage = $service->vehicle?->current_mileage !== null
                    ? (float) $service->vehicle->current_mileage
                    : null;

                $serviceStatus = 'no_target';
                $mileageUntilDue = null;

                if ($service->next_service_due_km !== null && $currentMileage !== null) {
                    $mileageUntilDue = (float) $service->next_service_due_km - $currentMileage;

                    if ($service->isDue($currentMileage)) {
                        $serviceStatus = 'due';
                    } elseif ($service->isDueSoon($currentMileage)) {
                        $serviceStatus = 'due_soon';
                    } else {
                        $serviceStatus = 'ok';
                    }
                }

                return [
                    'service' => $service,
                    'service_status' => $serviceStatus,
                    'mileage_until_due' => $mileageUntilDue,
                ];
            })
            ->filter(function (array $item) use ($status) {
                if (! in_array($status, ['due', 'due_soon', 'ok', 'no_target'], true)) {
                    return true;
                }

                return $item['service_status'] === $status;
            })
            ->values();

        return [
            'title' => 'Services report',
            'columns' => [
                ['key' => 'vehicle', 'label' => 'Vozilo'],
                ['key' => 'license_plate', 'label' => 'Tablice'],
                ['key' => 'service_type', 'label' => 'Tip servisa'],
                ['key' => 'service_date', 'label' => 'Datum servisa'],
                ['key' => 'next_service_due_km', 'label' => 'Sljedeći servis'],
                ['key' => 'mileage_until_due', 'label' => 'Preostalo km'],
                ['key' => 'status', 'label' => 'Stanje'],
            ],
            'rows' => $services->map(function (array $item) {
                /** @var VehicleService $service */
                $service = $item['service'];

                return [
                    'vehicle' => $service->vehicle?->name ?? '—',
                    'license_plate' => $service->vehicle?->license_plate ?: '—',
                    'service_type' => $service->service_type,
                    'service_date' => $service->service_date?->format('d.m.Y') ?? '—',
                    'next_service_due_km' => $service->next_service_due_km !== null
                        ? number_format((float) $service->next_service_due_km, 0, ',', '.') . ' km'
                        : '—',
                    'mileage_until_due' => $item['mileage_until_due'] !== null
                        ? number_format((float) $item['mileage_until_due'], 0, ',', '.') . ' km'
                        : '—',
                    'status' => $item['service_status'],
                ];
            })->all(),
        ];
    }

    private function assignmentsReport(Tenant $tenant, Request $request): array
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $assignments = VehicleAssignment::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'user:id,name,email',
                'vehicle:id,name,license_plate',
                'assignedBy:id,name',
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->whereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('name', 'like', "%{$search}%")->orWhere('license_plate', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($status, ['active', 'ended', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->latest('assigned_from')
            ->get();

        return [
            'title' => 'Vehicle assignments report',
            'columns' => [
                ['key' => 'user', 'label' => 'Korisnik'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'vehicle', 'label' => 'Vozilo'],
                ['key' => 'license_plate', 'label' => 'Tablice'],
                ['key' => 'assignment_type', 'label' => 'Tip zaduženja'],
                ['key' => 'assigned_from', 'label' => 'Od'],
                ['key' => 'assigned_until', 'label' => 'Do'],
                ['key' => 'unassigned_at', 'label' => 'Razduženo'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'assigned_by', 'label' => 'Dodijelio'],
            ],
            'rows' => $assignments->map(function (VehicleAssignment $assignment) {
                return [
                    'user' => $assignment->user?->name ?? '—',
                    'email' => $assignment->user?->email ?? '—',
                    'vehicle' => $assignment->vehicle?->name ?? '—',
                    'license_plate' => $assignment->vehicle?->license_plate ?: '—',
                    'assignment_type' => $assignment->assignment_type,
                    'assigned_from' => $assignment->assigned_from?->format('d.m.Y H:i') ?? '—',
                    'assigned_until' => $assignment->assigned_until?->format('d.m.Y H:i') ?? '—',
                    'unassigned_at' => $assignment->unassigned_at?->format('d.m.Y H:i') ?? '—',
                    'status' => $assignment->status,
                    'assigned_by' => $assignment->assignedBy?->name ?? '—',
                ];
            })->all(),
        ];
    }

    private function usersReport(Tenant $tenant, Request $request): array
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $role = $request->string('role')->toString();

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
            ->when(in_array($role, ['tenant_admin', 'tenant_user'], true), fn ($query) => $query->where('role', $role))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->get();

        return [
            'title' => 'Users report',
            'columns' => [
                ['key' => 'name', 'label' => 'Korisnik'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'role', 'label' => 'Rola'],
                ['key' => 'is_active', 'label' => 'Aktivan'],
                ['key' => 'active_assignments', 'label' => 'Aktivna zaduženja'],
                ['key' => 'all_assignments', 'label' => 'Ukupno zaduženja'],
                ['key' => 'last_login_at', 'label' => 'Posljednji login'],
            ],
            'rows' => $users->map(function (User $user) {
                return [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active ? 'Da' : 'Ne',
                    'active_assignments' => $user->active_vehicle_assignments_count,
                    'all_assignments' => $user->vehicle_assignments_count,
                    'last_login_at' => $user->last_login_at?->format('d.m.Y H:i') ?? '—',
                ];
            })->all(),
        ];
    }

    private function alertsReport(Tenant $tenant, User $user, Request $request): array
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $severity = $request->string('severity')->toString();

        $alerts = Alert::query()
            ->where('alerts.tenant_id', $tenant->id)
            ->leftJoin('alert_user_statuses as user_status', function ($join) use ($user) {
                $join->on('alerts.id', '=', 'user_status.alert_id')
                    ->where('user_status.user_id', '=', $user->id);
            })
            ->select([
                'alerts.*',
                DB::raw('COALESCE(user_status.is_read, 0) as user_is_read'),
                'user_status.read_at as user_read_at',
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('alerts.title', 'like', "%{$search}%")
                        ->orWhere('alerts.message', 'like', "%{$search}%")
                        ->orWhere('alerts.type', 'like', "%{$search}%");
                });
            })
            ->when(in_array($severity, ['info', 'low', 'medium', 'high'], true), fn ($query) => $query->where('alerts.severity', $severity))
            ->when($status === 'read', fn ($query) => $query->where('user_status.is_read', true))
            ->when($status === 'unread', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery
                        ->whereNull('user_status.id')
                        ->orWhere('user_status.is_read', false);
                });
            })
            ->orderByRaw('COALESCE(alerts.sent_at, alerts.created_at) desc')
            ->get();

        return [
            'title' => 'Alerts report',
            'columns' => [
                ['key' => 'type', 'label' => 'Tip'],
                ['key' => 'severity', 'label' => 'Ozbiljnost'],
                ['key' => 'title', 'label' => 'Naslov'],
                ['key' => 'message', 'label' => 'Poruka'],
                ['key' => 'is_read', 'label' => 'Pročitano'],
                ['key' => 'sent_at', 'label' => 'Poslano'],
                ['key' => 'read_at', 'label' => 'Pročitano u'],
            ],
            'rows' => $alerts->map(function (Alert $alert) {
                return [
                    'type' => $alert->type,
                    'severity' => $alert->severity,
                    'title' => $alert->title,
                    'message' => $alert->message,
                    'is_read' => (bool) ($alert->user_is_read ?? false) ? 'Da' : 'Ne',
                    'sent_at' => $alert->sent_at?->format('d.m.Y H:i') ?? '—',
                    'read_at' => $alert->user_read_at ? date('d.m.Y H:i', strtotime($alert->user_read_at)) : '—',
                ];
            })->all(),
        ];
    }
}
