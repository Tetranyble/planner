<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only authorization-code with S256 PKCE can enter Planner's consent flow. */
final class RequirePlannerOAuthAuthorizationRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->query('response_type') === 'code'
            && $request->query('code_challenge_method') === 'S256'
            && is_string($request->query('code_challenge'))
            && preg_match('/\A[A-Za-z0-9_-]{43}\z/D', $request->query('code_challenge')) === 1
            && $request->query('scope') === 'mcp:use'
            && is_string($request->query('state'))
            && strlen($request->query('state')) >= 16,
            400, 'Planner requires an OAuth authorization code, S256 PKCE and mcp:use scope.');

        return $next($request);
    }
}
