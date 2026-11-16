<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\GovernBrandClaims;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubmitBrandClaimForReviewController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, string $claimUuid, GovernBrandClaims $service, ApiResponse $response): JsonResponse
    {
        $service->submitClaim($request->user(), $organisation, $brand, $claimUuid);

        return $response->success(['status' => 'IN_REVIEW']);
    }
}
