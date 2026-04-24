<?php

namespace App\Http\Middleware;

use App\Models\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHttpHost());
        $host = preg_replace('/:\d+$/', '', $host);

        $tenantDomain = TenantDomain::query()
            ->with('tenant')
            ->where('domain', $host)
            ->where('is_active', true)
            ->first();

        abort_unless(
            $tenantDomain && $tenantDomain->tenant && $tenantDomain->tenant->is_active,
            404,
            'Tenant not found.'
        );

        app()->instance('currentTenant', $tenantDomain->tenant);
        app()->instance('currentTenantDomain', $tenantDomain);

        return $next($request);
    }
}
