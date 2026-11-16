<?php

namespace App\Http\Middleware;

use App\Authorization\BrandPermissionGuard;
use App\Authorization\CurrentOrganisation;
use App\Domain\Agent\AgentDelegationPolicy;
use App\Models\PlannerOAuthUser;
use App\Models\User;
use App\Repository\Contracts\BrandMemberRepositoryContract;
use App\Repository\Contracts\PlannerOAuthLinkRepositoryContract;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Token;
use Symfony\Component\HttpFoundation\Response;

/** A Passport bearer can use only the explicitly linked owner-issued agent delegation. */
final class RequirePlannerOAuthAgentGrant
{
    public function __construct(
        private readonly PlannerOAuthLinkRepositoryContract $links,
        private readonly BrandPermissionGuard $permissions,
        private readonly BrandMemberRepositoryContract $members,
        private readonly CurrentOrganisation $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('planner.oauth_enabled', false) && config('planner.agent_access_enabled', false),
            503, 'OAuth agent access is disabled.');
        abort_unless($request->bearerToken() !== null, 401, 'OAuth bearer token required.');
        $actor = Auth::guard('api')->user();
        $token = $actor instanceof PlannerOAuthUser ? $actor->currentAccessToken() : null;
        abort_unless($actor instanceof PlannerOAuthUser && $token instanceof Token
            && $token->revoked === false && $token->expires_at?->isFuture()
            && $token->scopes === ['mcp:use'] && $actor->tokenCan('mcp:use') && (int) $token->user_id === (int) $actor->id,
            401, 'A current, scope-limited OAuth token is required.');
        $scope = $this->links->scopeForPassportToken($actor, (string) $token->client_id);
        abort_unless($scope !== null, 403, 'OAuth consent or agent delegation has been revoked.');
        // Re-load the canonical User model for Kinship subject morph identity.
        // The OAuth-only subclass authenticates Passport but must not change role assignments.
        $principal = User::query()->findOrFail($actor->id);
        $this->context->select($principal, (int) $scope['organisation']->id);
        abort_unless($this->permissions->allows($principal, $scope['brand'], 'approval_request.index'),
            403, 'Current organisation/brand membership or permissions have changed.');
        if ($scope['delegation']->mode === AgentDelegationPolicy::DELEGATED_PUBLISH) {
            abort_unless($this->members->organisationMember($scope['organisation'], (int) $principal->id)?->role_name === 'owner',
                403, 'The granting owner no longer controls this organisation.');
        }
        if (! app()->environment('local', 'testing')) {
            abort_unless($request->isSecure(), 403, 'OAuth MCP requires HTTPS.');
        }
        // Tool implementations use request()->user(); do not let a browser session override Passport.
        $request->setUserResolver(static fn (): User => $principal);
        $request->attributes->set('planner_agent_scope', $scope);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
