<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminHost = config('app.admin_host');

        config([
            'session.cookie' => $request->getHost() === $adminHost
                ? 'mamontrack_admin_session'
                : 'mamontrack_tenant_session',
        ]);

        return $next($request);
    }
}
