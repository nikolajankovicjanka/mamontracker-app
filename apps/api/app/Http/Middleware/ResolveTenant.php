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
        $host = $request->getHost();
        $adminHost = config('app.admin_host', 'admin.localhost');

        if ($host === $adminHost) {
            return $next($request);
        }

        $tenantDomain = TenantDomain::with('tenant')
            ->where('domain', $host)
            ->where('is_active', true)
            ->first();

        if (! $tenantDomain || ! $tenantDomain->tenant || ! $tenantDomain->tenant->is_active) {
            abort(404, 'Tenant not found.');
        }

        app()->instance('currentTenant', $tenantDomain->tenant);
        app()->instance('currentTenantDomain', $tenantDomain);

        return $next($request);
    }
}
