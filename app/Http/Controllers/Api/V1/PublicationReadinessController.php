<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\AssessPublicationReadiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicationReadinessController extends Controller
{
    public function show(Request $request, Organisation $organisation, Brand $brand, string $itemUuid, string $targetUuid, int $requestId, AssessPublicationReadiness $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->assess($request->user(), $organisation, $brand, $itemUuid, $targetUuid, $requestId));
    }
}
