<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertUserStatus extends Model
{
    protected $fillable = [
        'alert_id',
        'user_id',
        'is_read',
        'seen_at',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'seen_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
