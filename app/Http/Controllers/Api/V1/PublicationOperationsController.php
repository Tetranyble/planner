<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Publishing\PublicationOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicationOperationsController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand,
        PublicationOperations $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->overview($request->user(), $organisation, $brand));
    }

    public function show(Request $request, Organisation $organisation, Brand $brand, int $jobId,
        PublicationOperations $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->history($request->user(), $organisation, $brand, $jobId));
    }
}
