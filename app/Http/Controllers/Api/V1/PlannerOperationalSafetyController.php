<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Integrations\PlannerOperationalSafety;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlannerOperationalSafetyController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        PlannerOperationalSafety $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->show($request->user(), $organisation, $brand));
    }
}
