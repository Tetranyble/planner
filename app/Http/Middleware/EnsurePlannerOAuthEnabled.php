<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlannerOAuthEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('planner.oauth_enabled', false), 503, 'Planner OAuth login is disabled.');
        if (! app()->environment('local', 'testing')) {
            abort_unless($request->isSecure() && str_starts_with((string) config('app.url'), 'https://'),
                403, 'Planner OAuth requires HTTPS.');
        }

        return $next($request);
    }
}
