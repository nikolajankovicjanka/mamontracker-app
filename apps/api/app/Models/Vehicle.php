<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'brand',
        'model',
        'production_year',
        'license_plate',
        'vin',
        'registration_expiry_date',
        'current_mileage',
        'status',
        'notes',
        'last_known_lat',
        'last_known_lng',
        'last_position_at',
        'last_speed_kph',
    ];

    protected $casts = [
        'registration_expiry_date' => 'date',
        'last_position_at' => 'datetime',
        'current_mileage' => 'decimal:2',
        'last_known_lat' => 'decimal:7',
        'last_known_lng' => 'decimal:7',
        'last_speed_kph' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function gpsDevices(): HasMany
    {
        return $this->hasMany(GpsDevice::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(VehicleService::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
