<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Analytics\ProviderPerformanceDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Invokable read-only analytics route; no mutation/AI/network requests. */
final class ProviderPerformanceDashboardController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        ProviderPerformanceDashboard $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->show($request->user(), $organisation, $brand));
    }
}
