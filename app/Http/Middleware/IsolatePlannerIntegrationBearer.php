<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/** Prevent scoped, read-only integration credentials from acting as normal user API tokens. */
final class IsolatePlannerIntegrationBearer
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() === null) {
            return $next($request);
        }
        $principal = Auth::guard('sanctum')->user();
        $token = $principal?->currentAccessToken();
        if ($token instanceof PersonalAccessToken && in_array('planner:read', $token->abilities ?? [], true)) {
            $allowed = $token->abilities === ['planner:read']
                && $request->is('api/v1/integrations/organisations/*') && $request->isMethod('GET');
            abort_unless($allowed, 403, 'Read-only Planner credentials cannot call normal application APIs.');
        }
        if ($token instanceof PersonalAccessToken && in_array('planner:agent', $token->abilities ?? [], true)) {
            $allowed = $token->abilities === ['planner:agent']
                && ($request->is('api/v1/agent/action') && $request->isMethod('POST')
                    || $request->is('api/v1/agent/work-orders') && $request->isMethod('GET'));
            abort_unless($allowed, 403, 'Agent credentials may only call the scoped agent orchestration API.');
        }

        return $next($request);
    }
}
