<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\GovernBrandClaims;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ActivateBrandApprovalPolicyController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, string $policyUuid, GovernBrandClaims $service, ApiResponse $response): JsonResponse
    {
        $service->activateApprovalPolicy($request->user(), $organisation, $brand, $policyUuid);

        return $response->success(['state' => 'ACTIVE']);
    }
}
