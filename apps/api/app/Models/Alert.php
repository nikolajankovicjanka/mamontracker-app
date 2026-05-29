<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alert extends Model
{
    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'gps_device_id',
        'type',
        'severity',
        'title',
        'message',
        'sent_at',
        'meta',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function gpsDevice(): BelongsTo
    {
        return $this->belongsTo(GpsDevice::class);
    }

    public function userStatuses(): HasMany
    {
        return $this->hasMany(AlertUserStatus::class);
    }
}
