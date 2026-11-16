<?php

namespace App\Http\Middleware;

use App\Authorization\BrandPermissionGuard;
use App\Authorization\CurrentOrganisation;
use App\Models\User;
use App\Repository\Contracts\PlannerIntegrationGrantRepositoryContract;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/** MCP POSTs are read-only *tool invocations*, never authorizations to mutate resources. */
final class RequirePlannerMcpGrant
{
    public function __construct(
        private readonly PlannerIntegrationGrantRepositoryContract $grants,
        private readonly CurrentOrganisation $context,
        private readonly BrandPermissionGuard $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('planner.integration_read_enabled', false)
            && config('planner.mcp_read_enabled', false), 503, 'Planner MCP is disabled.');
        abort_unless($request->bearerToken() !== null, 401, 'A bearer token is required.');
        // An agent/browser cannot borrow an ambient Sanctum cookie/session.
        $actor = $request->user();
        $token = $actor instanceof User ? $actor->currentAccessToken() : null;
        abort_unless($token instanceof PersonalAccessToken
            && $token->tokenable_type === $actor->getMorphClass()
            && (int) $token->tokenable_id === (int) $actor->getKey()
            && $token->abilities === ['planner:read']
            && ($token->expires_at === null || $token->expires_at->isFuture()), 403,
            'A valid read-only Planner credential is required.');

        if (! app()->environment('local', 'testing')) {
            abort_unless($request->isSecure()
                && str_starts_with((string) config('app.url'), 'https://'), 403,
                'HTTPS is required for the MCP endpoint.');
        }
        // Browser MCP clients supply an Origin header; no wildcard or suffix matching.
        $origin = $request->header('Origin');
        if ($origin !== null) {
            $allowed = array_values(array_filter(array_map('trim', explode(',',
                (string) config('planner.mcp_allowed_origins', '')))));
            abort_unless(in_array($origin, $allowed, true), 403, 'MCP Origin not allowed.');
        }
        $scope = $this->grants->scopeForToken((int) $token->getKey(), $actor);
        abort_unless($scope !== null, 403, 'Integration grant is expired or revoked.');
        $organisation = $scope['organisation'];
        $brand = $scope['brand'];
        abort_unless($this->grants->allows((int) $token->getKey(), $organisation, $brand, $actor),
            403, 'Integration scope not active.');
        $this->context->select($actor, (int) $organisation->getKey());
        abort_unless($this->permissions->allows($actor, $brand, 'approval_request.index'), 403,
            'The organisation or brand read permission is no longer active.');
        $request->attributes->set('planner_mcp_scope', $scope);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
