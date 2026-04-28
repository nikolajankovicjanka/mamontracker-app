<?php

namespace App\Observers;

use App\Models\GpsDevice;
use App\Models\GpsDeviceVehicleHistory;

class GpsDeviceObserver
{
    public function created(GpsDevice $gpsDevice): void
    {
        if ($gpsDevice->vehicle_id === null) {
            return;
        }

        GpsDeviceVehicleHistory::create([
            'tenant_id' => $gpsDevice->tenant_id,
            'gps_device_id' => $gpsDevice->id,
            'vehicle_id' => $gpsDevice->vehicle_id,
            'assigned_by' => auth()->id(),
            'assigned_at' => now(),
            'notes' => 'Initial assignment on device creation.',
        ]);
    }

    public function updated(GpsDevice $gpsDevice): void
    {
        if (! $gpsDevice->wasChanged('vehicle_id')) {
            return;
        }

        $oldVehicleId = $gpsDevice->getOriginal('vehicle_id');
        $newVehicleId = $gpsDevice->vehicle_id;

        if ($oldVehicleId !== null) {
            GpsDeviceVehicleHistory::query()
                ->where('gps_device_id', $gpsDevice->id)
                ->where('vehicle_id', $oldVehicleId)
                ->whereNull('unassigned_at')
                ->latest('assigned_at')
                ->first()
                ?->update([
                    'unassigned_at' => now(),
                    'notes' => $newVehicleId
                        ? 'Device reassigned to another vehicle.'
                        : 'Device unassigned from vehicle.',
                ]);
        }

        if ($newVehicleId !== null) {
            GpsDeviceVehicleHistory::create([
                'tenant_id' => $gpsDevice->tenant_id,
                'gps_device_id' => $gpsDevice->id,
                'vehicle_id' => $newVehicleId,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
                'notes' => $oldVehicleId
                    ? 'Device assigned after reassignment.'
                    : 'Device assigned to vehicle.',
            ]);
        }
    }
}
