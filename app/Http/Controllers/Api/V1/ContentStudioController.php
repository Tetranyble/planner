<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentStudio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ContentStudioController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand, ManageContentStudio $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->listing($request->user(), $organisation, $brand));
    }

    public function show(Request $request, Organisation $organisation, Brand $brand, string $itemUuid, ManageContentStudio $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->detail($request->user(), $organisation, $brand, $itemUuid));
    }
}
