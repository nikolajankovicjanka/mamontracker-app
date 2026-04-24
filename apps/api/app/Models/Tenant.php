<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'plan',
        'settings',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'meta' => 'array',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function primaryDomain(): HasOne
    {
        return $this->hasOne(TenantDomain::class)
            ->where('is_primary', true);
    }

    public function activeDomains(): HasMany
    {
        return $this->hasMany(TenantDomain::class)
            ->where('is_active', true);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function gpsDevices(): HasMany
    {
        return $this->hasMany(GpsDevice::class);
    }

    public function vehicleServices(): HasMany
    {
        return $this->hasMany(VehicleService::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isOnPlan(?string $plan): bool
    {
        if ($plan === null) {
            return false;
        }

        return $this->plan === $plan;
    }
}
