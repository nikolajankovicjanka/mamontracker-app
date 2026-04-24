<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'is_read',
        'sent_at',
        'seen_at',
        'meta',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'sent_at' => 'datetime',
        'seen_at' => 'datetime',
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

    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'seen_at' => now(),
        ]);
    }

    public function isUnread(): bool
    {
        return ! $this->is_read;
    }

    public function isCritical(): bool
    {
        return in_array($this->severity, ['high', 'critical'], true);
    }
}
