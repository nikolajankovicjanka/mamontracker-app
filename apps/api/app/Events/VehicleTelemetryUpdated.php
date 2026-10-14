<?php

namespace App\Events;

use App\Models\Vehicle;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VehicleTelemetryUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Vehicle $vehicle,
        public bool $online,
        public array $telemetry = [],
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->vehicle->tenant_id}.vehicles"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'vehicle.telemetry.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'vehicle' => [
                'id' => $this->vehicle->id,
                'name' => $this->vehicle->name,
                'license_plate' => $this->vehicle->license_plate,
                'online' => $this->online,
                'lat' => $this->vehicle->last_known_lat !== null
                    ? (float) $this->vehicle->last_known_lat
                    : null,
                'lng' => $this->vehicle->last_known_lng !== null
                    ? (float) $this->vehicle->last_known_lng
                    : null,
                'last_position_at' => $this->vehicle->last_position_at?->toISOString(),
                'last_speed_kph' => $this->vehicle->last_speed_kph !== null
                    ? (float) $this->vehicle->last_speed_kph
                    : null,
                'current_mileage' => $this->vehicle->current_mileage !== null
                    ? (float) $this->vehicle->current_mileage
                    : null,
            ],
            'telemetry' => $this->telemetry,
        ];
    }
}
