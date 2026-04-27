<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPlatformSuperAdmin(): bool
    {
        return $this->role === 'platform_super_admin';
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === 'tenant_admin';
    }

    public function isTenantUser(): bool
    {
        return $this->role === 'tenant_user';
    }

    public function isTenantScoped(): bool
    {
        return in_array($this->role, ['tenant_admin', 'tenant_user'], true);
    }

    public function belongsToTenant(int|string|null $tenantId): bool
    {
        if ($tenantId === null || $this->tenant_id === null) {
            return false;
        }

        return (string) $this->tenant_id === (string) $tenantId;
    }

    public function canAccessTenant(int|string|null $tenantId): bool
    {
        if ($this->isPlatformSuperAdmin()) {
            return true;
        }

        return $this->belongsToTenant($tenantId);
    }

    public function canManageTenantData(): bool
    {
        return $this->isPlatformSuperAdmin() || $this->isTenantAdmin();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isPlatformSuperAdmin() && $this->is_active;
    }
}
