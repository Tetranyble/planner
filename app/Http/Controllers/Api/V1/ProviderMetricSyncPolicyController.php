<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Analytics\ManageProviderMetricSyncSchedules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProviderMetricSyncPolicyController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand,
        ManageProviderMetricSyncSchedules $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->show($request->user(), $organisation, $brand));
    }

    public function update(Request $request, Organisation $organisation, Brand $brand,
        ManageProviderMetricSyncSchedules $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'],
            'utc_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'max_posts_per_day' => ['required', 'integer', 'min:1', 'max:20'],
            'version' => ['required', 'integer', 'min:0']]);

        return $response->success($service->update($request->user(), $organisation, $brand, $data));
    }
}
