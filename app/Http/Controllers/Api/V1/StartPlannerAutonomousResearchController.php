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
use Illuminate\Validation\Rule;

final class StartPlannerAutonomousResearchController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        ManagePlannerAgentDelegations $manager, OrchestratePlannerAgent $orchestrator, ApiResponse $response): JsonResponse
    {
        $input = $request->validate([
            'delegation_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:12', 'max:96', 'regex:/\A[a-zA-Z0-9:_-]+\z/D'],
            'run_key' => ['required', 'string', 'min:12', 'max:96', 'regex:/\A[a-zA-Z0-9:_-]+\z/D'],
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'objective' => ['required', 'string', 'min:5', 'max:3000'],
            'resource_kind' => ['required', Rule::in(['SOCIAL_COPY', 'CAMPAIGN_BRIEF'])],
        ]);
        $payload = array_diff_key($input, ['delegation_id' => true, 'idempotency_key' => true]);
        $result = $manager->startResearch($request->user(), $organisation, $brand, (int) $input['delegation_id'],
            $payload, $input['idempotency_key'], $orchestrator);

        return $response->success($result, statusCode: 202, headers: ['Cache-Control' => 'private, no-store']);
    }
}
