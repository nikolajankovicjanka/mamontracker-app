<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\VehicleService;
use Illuminate\Console\Command;

class GenerateOperationalAlerts extends Command
{
    protected $signature = 'app:generate-operational-alerts {--tenant= : Generate alerts only for a specific tenant ID}';

    protected $description = 'Generate tenant alerts for registrations and services';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $tenants = Tenant::query()
            ->when($tenantId, fn ($query) => $query->whereKey($tenantId))
            ->where('is_active', true)
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');
            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->info("Generating alerts for tenant #{$tenant->id} ({$tenant->name})");

            $this->generateRegistrationAlerts($tenant->id);
            $this->generateServiceAlerts($tenant->id);
        }

        $this->info('Operational alerts generated successfully.');

        return self::SUCCESS;
    }

    private function generateRegistrationAlerts(int $tenantId): void
    {
        $vehicles = Vehicle::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('registration_expiry_date')
            ->get();

        foreach ($vehicles as $vehicle) {
            $daysLeft = now()->startOfDay()->diffInDays($vehicle->registration_expiry_date, false);
            $registrationExpiryDate = $vehicle->registration_expiry_date?->toDateString();

            if ($daysLeft < 0) {
                $this->createAlertIfMissing(
                    data: [
                        'tenant_id' => $tenantId,
                        'vehicle_id' => $vehicle->id,
                        'gps_device_id' => null,
                        'type' => 'registration_expired',
                        'severity' => 'high',
                        'title' => 'Registracija je istekla',
                        'message' => sprintf(
                            'Vozilu %s (%s) je istekla registracija.',
                            $vehicle->name,
                            $vehicle->license_plate ?: 'bez tablica'
                        ),
                        'sent_at' => now(),
                        'meta' => [
                            'route_name' => 'registration-edit',
                            'route_params' => [
                                'vehicleId' => $vehicle->id,
                            ],
                            'vehicle_id' => $vehicle->id,
                            'registration_expiry_date' => $registrationExpiryDate,
                        ],
                    ],
                    dedupeMeta: [
                        'vehicle_id' => $vehicle->id,
                        'registration_expiry_date' => $registrationExpiryDate,
                    ],
                );

                continue;
            }

            if (! in_array($daysLeft, [7, 3, 1, 0], true)) {
                continue;
            }

            $title = match ($daysLeft) {
                0 => 'Registracija ističe danas',
                1 => 'Registracija ističe za 1 dan',
                default => "Registracija ističe za {$daysLeft} dana",
            };

            $message = match ($daysLeft) {
                0 => sprintf(
                    'Vozilu %s (%s) registracija ističe danas.',
                    $vehicle->name,
                    $vehicle->license_plate ?: 'bez tablica'
                ),
                1 => sprintf(
                    'Vozilu %s (%s) registracija ističe za 1 dan.',
                    $vehicle->name,
                    $vehicle->license_plate ?: 'bez tablica'
                ),
                default => sprintf(
                    'Vozilu %s (%s) registracija ističe za %d dana.',
                    $vehicle->name,
                    $vehicle->license_plate ?: 'bez tablica',
                    $daysLeft
                ),
            };

            $this->createAlertIfMissing(
                data: [
                    'tenant_id' => $tenantId,
                    'vehicle_id' => $vehicle->id,
                    'gps_device_id' => null,
                    'type' => 'registration_expiring',
                    'severity' => $daysLeft <= 1 ? 'high' : 'medium',
                    'title' => $title,
                    'message' => $message,
                    'sent_at' => now(),
                    'meta' => [
                        'route_name' => 'registration-edit',
                        'route_params' => [
                            'vehicleId' => $vehicle->id,
                        ],
                        'vehicle_id' => $vehicle->id,
                        'days_left' => $daysLeft,
                        'registration_expiry_date' => $registrationExpiryDate,
                    ],
                ],
                dedupeMeta: [
                    'vehicle_id' => $vehicle->id,
                    'days_left' => $daysLeft,
                    'registration_expiry_date' => $registrationExpiryDate,
                ],
            );
        }
    }

    private function generateServiceAlerts(int $tenantId): void
    {
        $services = VehicleService::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'vehicle:id,name,license_plate,current_mileage',
            ])
            ->get();

        foreach ($services as $service) {
            if (! $service->vehicle || $service->next_service_due_km === null || $service->vehicle->current_mileage === null) {
                continue;
            }

            $currentMileage = (float) $service->vehicle->current_mileage;
            $mileageUntilDue = (float) $service->next_service_due_km - $currentMileage;

            if ($service->isDue($currentMileage)) {
                $this->createAlertIfMissing(
                    data: [
                        'tenant_id' => $tenantId,
                        'vehicle_id' => $service->vehicle_id,
                        'gps_device_id' => null,
                        'type' => 'service_due',
                        'severity' => 'high',
                        'title' => 'Servis je dospio',
                        'message' => sprintf(
                            "Servis '%s' za vozilo %s (%s) je dospio.",
                            $service->service_type,
                            $service->vehicle->name,
                            $service->vehicle->license_plate ?: 'bez tablica'
                        ),
                        'sent_at' => now(),
                        'meta' => [
                            'route_name' => 'service-show',
                            'route_params' => [
                                'id' => $service->id,
                            ],
                            'service_id' => $service->id,
                            'vehicle_id' => $service->vehicle_id,
                            'next_service_due_km' => (float) $service->next_service_due_km,
                        ],
                    ],
                    dedupeMeta: [
                        'service_id' => $service->id,
                        'next_service_due_km' => (float) $service->next_service_due_km,
                    ],
                );

                continue;
            }

            if ($service->isDueSoon($currentMileage)) {
                $this->createAlertIfMissing(
                    data: [
                        'tenant_id' => $tenantId,
                        'vehicle_id' => $service->vehicle_id,
                        'gps_device_id' => null,
                        'type' => 'service_due_soon',
                        'severity' => 'medium',
                        'title' => 'Servis uskoro dospijeva',
                        'message' => sprintf(
                            "Servis '%s' za vozilo %s (%s) dospijeva za približno %s km.",
                            $service->service_type,
                            $service->vehicle->name,
                            $service->vehicle->license_plate ?: 'bez tablica',
                            number_format(max($mileageUntilDue, 0), 0, ',', '.')
                        ),
                        'sent_at' => now(),
                        'meta' => [
                            'route_name' => 'service-show',
                            'route_params' => [
                                'id' => $service->id,
                            ],
                            'service_id' => $service->id,
                            'vehicle_id' => $service->vehicle_id,
                            'mileage_until_due' => $mileageUntilDue,
                            'next_service_due_km' => (float) $service->next_service_due_km,
                        ],
                    ],
                    dedupeMeta: [
                        'service_id' => $service->id,
                        'next_service_due_km' => (float) $service->next_service_due_km,
                    ],
                );
            }
        }
    }

    private function createAlertIfMissing(array $data, array $dedupeMeta = []): void
    {
        $query = Alert::query()
            ->where('tenant_id', $data['tenant_id'])
            ->where('type', $data['type']);

        if (array_key_exists('vehicle_id', $data)) {
            $data['vehicle_id'] === null
                ? $query->whereNull('vehicle_id')
                : $query->where('vehicle_id', $data['vehicle_id']);
        }

        if (array_key_exists('gps_device_id', $data)) {
            $data['gps_device_id'] === null
                ? $query->whereNull('gps_device_id')
                : $query->where('gps_device_id', $data['gps_device_id']);
        }

        foreach ($dedupeMeta as $key => $value) {
            $query->where("meta->{$key}", $value);
        }

        if ($query->exists()) {
            return;
        }

        Alert::create($data);
    }
}
