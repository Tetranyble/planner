<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Publishing\VerifySocialPublishingCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VerifySocialPublishingCapabilityController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        string $accountUuid, VerifySocialPublishingCapability $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->verify($request->user(), $organisation, $brand, $accountUuid));
    }
}
