<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Analytics\ManageProviderMetricSyncs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProviderMetricSyncController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand,
        ManageProviderMetricSyncs $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->index($request->user(), $organisation, $brand));
    }

    public function store(Request $request, Organisation $organisation, Brand $brand,
        ManageProviderMetricSyncs $service, ApiResponse $response): JsonResponse
    {
        $values = $request->validate([
            'published_post_ids' => ['required', 'array', 'min:1', 'max:20'],
            'published_post_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        return $response->success($service->queue($request->user(), $organisation, $brand, $values['published_post_ids']));
    }
}
