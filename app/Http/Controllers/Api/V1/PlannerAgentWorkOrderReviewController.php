<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Agents\ManagePlannerAgentDelegations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlannerAgentWorkOrderReviewController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, string $uuid,
        ManagePlannerAgentDelegations $manager, ApiResponse $response): JsonResponse
    {
        return $response->success($manager->detail($request->user(), $organisation, $brand, $uuid),
            headers: ['Cache-Control' => 'private, no-store']);
    }
}
