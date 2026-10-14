<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'tenant.{tenantId}.vehicles',
    function (User $user, int $tenantId): bool {
        return $user->canAccessTenant($tenantId);
    }
);
