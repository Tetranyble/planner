<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Publishing\ManageMediaPublicationRuns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MediaPublicationRunController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand,
        ManageMediaPublicationRuns $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->list($request->user(), $organisation, $brand));
    }

    public function show(Request $request, Organisation $organisation, Brand $brand, int $runId,
        ManageMediaPublicationRuns $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->show($request->user(), $organisation, $brand, $runId));
    }

    public function store(Request $request, Organisation $organisation, Brand $brand,
        ManageMediaPublicationRuns $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validate(['preparation_id' => ['required', 'integer', 'min:1']]);

        return $response->success($service->create($request->user(), $organisation, $brand, (int) $data['preparation_id']));
    }
}
