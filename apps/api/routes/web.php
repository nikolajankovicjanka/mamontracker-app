<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
    $tenantDomain = app()->bound('currentTenantDomain') ? app('currentTenantDomain') : null;

    return [
        'tenant_name' => $tenant?->name,
        'tenant_slug' => $tenant?->slug,
        'domain' => $tenantDomain?->domain,
    ];
});
