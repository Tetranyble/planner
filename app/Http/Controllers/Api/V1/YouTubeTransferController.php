<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Publishing\ManageYouTubeTransfers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class YouTubeTransferController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand,
        ManageYouTubeTransfers $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->list($request->user(), $organisation, $brand));
    }

    public function show(Request $request, Organisation $organisation, Brand $brand, int $transferId,
        ManageYouTubeTransfers $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->show($request->user(), $organisation, $brand, $transferId));
    }

    public function store(Request $request, Organisation $organisation, Brand $brand,
        ManageYouTubeTransfers $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validate(['media_run_id' => ['required', 'integer', 'min:1']]);

        return $response->success($service->create($request->user(), $organisation, $brand, (int) $data['media_run_id']));
    }
}
