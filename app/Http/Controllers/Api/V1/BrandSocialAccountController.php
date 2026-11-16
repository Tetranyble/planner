<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Social\ManageSocialConnections;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BrandSocialAccountController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand,
        ManageSocialConnections $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->directory($request->user(), $organisation, $brand));
    }
}
