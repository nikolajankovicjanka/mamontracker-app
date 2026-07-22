<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureSessionCookie
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $adminHost = config('app.admin_host');

        if ($request->getHost() === $adminHost) {
            config([
                'session.cookie' => 'mamontrack_admin_session',
            ]);
        } else {
            config([
                'session.cookie' => 'mamontrack_tenant_session',
            ]);
        }

        logger()->info('Session cookie', [
            'host' => $request->getHost(),
            'cookie' => config('session.cookie'),
        ]);

        return $next($request);
    }
}
