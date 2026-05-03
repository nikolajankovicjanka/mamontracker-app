<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantHasFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = current_tenant();
        $user = $request->user();

        if (! $tenant || ! $tenant->is_active || ! $user || ! $user->canAccessTenant($tenant->id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $tenant->hasFeature($feature)) {
            return response()->json([
                'message' => 'This feature is not available for your current package.',
                'feature' => $feature,
            ], 403);
        }

        return $next($request);
    }
}
