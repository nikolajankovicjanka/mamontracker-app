<?php

namespace App\Console\Commands;

use App\Models\GpsDevice;
use App\Services\Traccar\TraccarService;
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

            $gpsDevice->forceFill([
                'traccar_device_id' => $traccarDeviceId,
                'is_active' => data_get($remoteDevice, 'status') === 'online',
                'last_payload' => $remoteDevice,
                'last_sync_at' => now(),
            ])->save();

            if ($positionId) {
                try {
                    $position = $traccarService->getPositionById($positionId);

                    if ($position && $gpsDevice->vehicle) {
                        $gpsDevice->vehicle->forceFill([
                            'last_known_lat' => data_get($position, 'latitude'),
                            'last_known_lng' => data_get($position, 'longitude'),
                            'last_position_at' => $this->resolvePositionTime($position),
                            'last_speed_kph' => $this->resolveSpeedKph($position),
                        ])->save();
                    }
                } catch (Throwable $exception) {
                    $this->warn("Failed to sync latest position for IMEI {$uniqueId}: {$exception->getMessage()}");
                }
            }

            $syncedCount++;
            $this->line("Synced IMEI {$uniqueId}");
        }

        $this->info("Traccar sync completed. Synced: {$syncedCount}, Skipped: {$skippedCount}");

        return self::SUCCESS;
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
