<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\ManageBrandStrategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BrandStrategyController extends Controller
{
    public function show(Request $request, Organisation $organisation, Brand $brand, ManageBrandStrategy $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->read($request->user(), $organisation, $brand));
    }
}
