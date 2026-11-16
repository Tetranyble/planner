<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Agents\OrchestratePlannerAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlannerAgentWorkOrdersController extends Controller
{
    public function __invoke(Request $request, OrchestratePlannerAgent $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->listing($request->user(), $request->attributes->get('planner_agent_scope')),
            headers: ['Cache-Control' => 'private, no-store']);
    }
}
