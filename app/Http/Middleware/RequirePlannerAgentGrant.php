<?php

namespace App\Http\Middleware;

use App\Authorization\BrandPermissionGuard;
use App\Authorization\CurrentOrganisation;
use App\Models\User;
use App\Repository\Contracts\PlannerAgentRepositoryContract;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class RequirePlannerAgentGrant
{
    public function __construct(private readonly PlannerAgentRepositoryContract $agents,
        private readonly CurrentOrganisation $context, private readonly BrandPermissionGuard $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('planner.agent_access_enabled', false), 503, 'Agent access is disabled.');
        abort_unless($request->bearerToken() !== null, 401, 'An explicit agent bearer token is required.');
        $actor = $request->user();
        $token = $actor instanceof User ? $actor->currentAccessToken() : null;
        abort_unless($token instanceof PersonalAccessToken && $token->abilities === ['planner:agent']
         && $token->tokenable_type === $actor->getMorphClass()
         && (int) $token->tokenable_id === (int) $actor->id
         && (! $token->expires_at || $token->expires_at->isFuture()), 403, 'A current agent grant is required.');
        if (! app()->environment('local', 'testing')) {
            abort_unless($request->isSecure() && str_starts_with((string) config('app.url'), 'https://'), 403, 'Agent API requires HTTPS.');
        }
        if ($origin = $request->header('Origin')) {
            $allowed = array_values(array_filter(array_map('trim', explode(',', (string) config('planner.agent_allowed_origins', '')))));
            abort_unless(in_array($origin, $allowed, true), 403, 'Agent origin is not allowed.');
        }
        $scope = $this->agents->scopeForToken((int) $token->getKey(), $actor);
        abort_unless($scope !== null, 403, 'Agent token expired or revoked.');
        $this->context->select($actor, (int) $scope['organisation']->id);
        abort_unless($this->permissions->allows($actor, $scope['brand'], 'approval_request.index'), 403,
            'Current organisation/brand membership or agent permissions have changed.');
        $request->attributes->set('planner_agent_scope', $scope);
        $result = $next($request);
        $result->headers->set('Cache-Control','private, no-store');
        $result->headers->set('X-Content-Type-Options','nosniff');

        return $result;
    }
}
