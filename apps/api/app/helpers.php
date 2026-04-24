<?php

use App\Models\Tenant;
use App\Models\TenantDomain;

if (! function_exists('current_tenant')) {
    function current_tenant(): ?Tenant
    {
        return app()->bound('currentTenant') ? app('currentTenant') : null;
    }
}

if (! function_exists('current_tenant_domain')) {
    function current_tenant_domain(): ?TenantDomain
    {
        return app()->bound('currentTenantDomain') ? app('currentTenantDomain') : null;
    }
}
