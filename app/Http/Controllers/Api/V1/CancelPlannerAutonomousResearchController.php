<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Agents\ManagePlannerAgentDelegations;
use App\Services\Agents\OrchestratePlannerAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CancelPlannerAutonomousResearchController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, string $uuid,
        ManagePlannerAgentDelegations $manager, OrchestratePlannerAgent $orchestrator, ApiResponse $response): JsonResponse
    {
        $input = $request->validate([
            'delegation_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:12', 'max:96', 'regex:/\A[a-zA-Z0-9:_-]+\z/D'],
        ]);

        return $response->success($manager->cancelResearch($request->user(), $organisation, $brand,
            (int) $input['delegation_id'], $uuid, $input['idempotency_key'], $orchestrator),
            headers: ['Cache-Control' => 'private, no-store']);
    }
}
