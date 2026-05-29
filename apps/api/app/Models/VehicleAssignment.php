<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleAssignment extends Model
{
    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'user_id',
        'assigned_by',
        'assignment_type',
        'assigned_from',
        'assigned_until',
        'unassigned_at',
        'status',
        'notes',
        'start_mileage',
        'end_mileage',
    ];

    protected $casts = [
        'assigned_from' => 'datetime',
        'assigned_until' => 'datetime',
        'unassigned_at' => 'datetime',
        'start_mileage' => 'decimal:2',
        'end_mileage' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->unassigned_at === null;
    }
}
