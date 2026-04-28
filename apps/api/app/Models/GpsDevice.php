<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GpsDevice extends Model
{
    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'provider',
        'device_name',
        'model',
        'imei',
        'traccar_device_id',
        'sim_number',
        'capabilities',
        'is_active',
        'last_payload',
        'last_sync_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_payload' => 'array',
        'capabilities' => 'array',
        'last_sync_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function assignmentHistory(): HasMany
    {
        return $this->hasMany(GpsDeviceVehicleHistory::class);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isLinkedToVehicle(): bool
    {
        return $this->vehicle_id !== null;
    }

    public function hasTraccarDevice(): bool
    {
        return $this->traccar_device_id !== null;
    }

    public function hasCapabilities(): bool
    {
        return ! empty($this->capabilities);
    }

    public function supports(string $feature): bool
    {
        return (bool) data_get($this->capabilities, $feature, false);
    }
}
