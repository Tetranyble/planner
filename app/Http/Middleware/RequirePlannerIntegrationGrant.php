<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\PlannerIntegrationGrantRepositoryContract;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class RequirePlannerIntegrationGrant
{
    public function __construct(private readonly PlannerIntegrationGrantRepositoryContract $grants) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('planner.integration_read_enabled', false), 503, 'Planner integrations are disabled.');
        $actor = $request->user();
        $token = $actor instanceof User ? $actor->currentAccessToken() : null;
        // Sanctum's first-party SPA transient token authorizes every ability; never allow it here.
        abort_unless($token instanceof PersonalAccessToken &&
            $token->can('planner:read') && $token->tokenable_type === $actor->getMorphClass() &&
            (int) $token->tokenable_id === (int) $actor->id, 403, 'A scoped Planner token is required.');
        $org = $request->route('organisation');
        $brand = $request->route('brand');
        abort_unless($org instanceof Organisation && $brand instanceof Brand &&
            (int) $brand->organisation_id === (int) $org->id &&
            $this->grants->allows((int) $token->getKey(), $org, $brand, $actor), 403,
            'This token is not valid for the selected organisation and brand.');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
