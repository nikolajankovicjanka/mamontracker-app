<?php

namespace App\Console\Commands;

use App\Models\GpsDevice;
use App\Services\Traccar\TraccarService;
use App\Support\TraccarTelemetry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class SyncTraccarDevicesCommand extends Command
{
    protected $signature = 'traccar:sync-devices';

    protected $description = 'Sync Traccar devices and latest positions into local GPS devices and vehicles';

    public function handle(TraccarService $traccarService): int
    {
        $this->info('Starting Traccar device sync...');

        try {
            $devices = $traccarService->getDevices();
        } catch (Throwable $exception) {
            $this->error('Failed to fetch devices from Traccar: ' . $exception->getMessage());

            return self::FAILURE;
        }

        if (empty($devices)) {
            $this->warn('No devices returned from Traccar.');

            return self::SUCCESS;
        }

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($devices as $remoteDevice) {
            $uniqueId = (string) data_get($remoteDevice, 'uniqueId', '');
            $traccarDeviceId = data_get($remoteDevice, 'id');
            $positionId = data_get($remoteDevice, 'positionId');

            if ($uniqueId === '') {
                $skippedCount++;
                $this->warn('Skipped remote device without uniqueId.');
                continue;
            }

            /** @var GpsDevice|null $gpsDevice */
            $gpsDevice = GpsDevice::query()
                ->where('imei', $uniqueId)
                ->first();

            if (! $gpsDevice) {
                $skippedCount++;
                $this->warn("Skipped device with IMEI {$uniqueId} because it does not exist locally.");
                continue;
            }

            $position = null;
            $telemetry = null;
            $previousPayload = is_array($gpsDevice->last_payload) ? $gpsDevice->last_payload : [];
            $previousTelemetry = data_get($previousPayload, 'telemetry');

            if (! is_array($previousTelemetry)) {
                $previousTelemetry = [];
            }

            if ($positionId) {
                try {
                    $position = $traccarService->getPositionById($positionId);

                    if ($position) {
                        $latestTelemetry = TraccarTelemetry::fromPosition($position);
                        $telemetry = $this->mergeTelemetryWithFallback($latestTelemetry, $previousTelemetry);

                        if ($gpsDevice->vehicle) {
                            $gpsDevice->vehicle->forceFill([
                                'last_known_lat' => data_get($position, 'latitude'),
                                'last_known_lng' => data_get($position, 'longitude'),
                                'last_position_at' => $this->resolvePositionTime($position),
                                'last_speed_kph' => $this->resolveSpeedKph($position),
                            ])->save();
                        }
                    }
                } catch (Throwable $exception) {
                    $this->warn("Failed to sync latest position for IMEI {$uniqueId}: {$exception->getMessage()}");
                }
            }

            if ($telemetry === null && ! empty($previousTelemetry)) {
                $telemetry = $previousTelemetry;
            }

            $gpsDevice->forceFill([
                'traccar_device_id' => $traccarDeviceId,
                'is_active' => data_get($remoteDevice, 'status') === 'online',
                'last_payload' => [
                    'device' => $remoteDevice,
                    'position' => $position,
                    'telemetry' => $telemetry,
                ],
                'last_sync_at' => now(),
            ])->save();

            $syncedCount++;
            $this->line("Synced IMEI {$uniqueId}");
        }

        $this->info("Traccar sync completed. Synced: {$syncedCount}, Skipped: {$skippedCount}");

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $latestTelemetry
     * @param array<string, mixed> $previousTelemetry
     * @return array<string, mixed>
     */
    protected function mergeTelemetryWithFallback(array $latestTelemetry, array $previousTelemetry): array
    {
        $fallbackFields = [
            'vin',
            'oem_fuel_level',
            'oem_total_mileage',
            'battery_current',
        ];

        foreach ($fallbackFields as $field) {
            if (($latestTelemetry[$field] ?? null) === null && array_key_exists($field, $previousTelemetry)) {
                $latestTelemetry[$field] = $previousTelemetry[$field];
            }
        }

        $latestRawIo = data_get($latestTelemetry, 'raw_io', []);
        $previousRawIo = data_get($previousTelemetry, 'raw_io', []);

        if (! is_array($latestRawIo)) {
            $latestRawIo = [];
        }

        if (! is_array($previousRawIo)) {
            $previousRawIo = [];
        }

        $rawIoFallbackFields = [
            'io389',
            'io390',
            'io69',
        ];

        foreach ($rawIoFallbackFields as $field) {
            if (($latestRawIo[$field] ?? null) === null && array_key_exists($field, $previousRawIo)) {
                $latestRawIo[$field] = $previousRawIo[$field];
            }
        }

        $latestTelemetry['raw_io'] = $latestRawIo;

        if (($latestTelemetry['oem_total_mileage'] ?? null) === null && ($latestRawIo['io389'] ?? null) !== null) {
            $latestTelemetry['oem_total_mileage'] = $latestRawIo['io389'];
        }

        if (($latestTelemetry['oem_fuel_level'] ?? null) === null && ($latestRawIo['io390'] ?? null) !== null) {
            $latestTelemetry['oem_fuel_level'] = $latestRawIo['io390'];
        }

        if (($latestTelemetry['battery_current'] ?? null) === null && ($latestRawIo['io69'] ?? null) !== null) {
            $latestTelemetry['battery_current'] = $latestRawIo['io69'];
        }

        return $latestTelemetry;
    }

    protected function resolvePositionTime(array $position): ?Carbon
    {
        $fixTime = data_get($position, 'fixTime');

        if (! $fixTime) {
            return null;
        }

        return Carbon::parse($fixTime);
    }

    protected function resolveSpeedKph(array $position): ?float
    {
        $speedKnots = data_get($position, 'speed');

        if ($speedKnots === null) {
            return null;
        }

        return round(((float) $speedKnots) * 1.852, 2);
    }
}
