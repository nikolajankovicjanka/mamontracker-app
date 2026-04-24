<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleService extends Model
{
    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'created_by',
        'service_type',
        'service_date',
        'mileage_at_service',
        'next_service_due_km',
        'notes',
    ];

    protected $casts = [
        'service_date' => 'date',
        'mileage_at_service' => 'decimal:2',
        'next_service_due_km' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDue(float|int $currentMileage): bool
    {
        return $this->next_service_due_km !== null
            && (float) $currentMileage >= (float) $this->next_service_due_km;
    }

    public function isDueSoon(float|int $currentMileage, float|int $threshold = 1000): bool
    {
        if ($this->next_service_due_km === null) {
            return false;
        }

        $remaining = (float) $this->next_service_due_km - (float) $currentMileage;

        return $remaining >= 0 && $remaining <= (float) $threshold;
    }
}
