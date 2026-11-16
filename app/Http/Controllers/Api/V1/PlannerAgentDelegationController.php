<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Domain\Agent\AgentDelegationPolicy;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Agents\ManagePlannerAgentDelegations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PlannerAgentDelegationController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand,
        ManagePlannerAgentDelegations $manager, ApiResponse $response): JsonResponse
    {
        return $response->success($manager->listing($request->user(), $organisation, $brand), headers: ['Cache-Control' => 'private, no-store']);
    }

    public function store(Request $request, Organisation $organisation, Brand $brand,
        ManagePlannerAgentDelegations $manager, ApiResponse $response): JsonResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'], 'expires_in_days' => ['required', 'integer', 'min:1', 'max:7'],
            'mode' => ['required', Rule::in([AgentDelegationPolicy::REVIEW_REQUIRED, AgentDelegationPolicy::DELEGATED_PUBLISH])],
            'account_uuids' => ['required', 'array', 'max:10'], 'account_uuids.*' => ['uuid', 'distinct'],
            'daily_action_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'daily_arm_limit' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        return $response->success($manager->issue($request->user(), $organisation, $brand, $input), statusCode: 201,
            headers: ['Cache-Control' => 'private, no-store']);
    }

    public function destroy(Request $request, Organisation $organisation, Brand $brand, int $grantId,
        ManagePlannerAgentDelegations $manager, ApiResponse $response): JsonResponse
    {
        return $response->success($manager->revoke($request->user(), $organisation, $brand, $grantId));
    }
}
