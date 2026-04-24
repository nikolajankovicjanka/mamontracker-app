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
        'is_active',
        'last_payload',
        'last_sync_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_payload' => 'array',
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
}
