<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Domain\Agent\AgentDelegationPolicy;
use App\Http\Controllers\Controller;
use App\Services\Agents\OrchestratePlannerAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PlannerAgentActionController extends Controller
{
    public function __invoke(Request $request, OrchestratePlannerAgent $service, ApiResponse $response): JsonResponse
    {
        $input = $request->validate(['action' => ['required', Rule::in(AgentDelegationPolicy::ACTIONS)],
            'idempotency_key' => ['required', 'string', 'min:12', 'max:96'],
            'payload' => ['required', 'array']]);

        return $response->success($service->execute($request->user(), $request->attributes->get('planner_agent_scope'),
            $input['action'], $input['idempotency_key'], $input['payload']), headers: ['Cache-Control' => 'private, no-store']);
    }
}
