<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\UpdateBrandProfileRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\ManageBrandStrategy;
use Illuminate\Http\JsonResponse;

final class BrandProfileController extends Controller
{
    public function update(UpdateBrandProfileRequest $request, Organisation $organisation, Brand $brand, ManageBrandStrategy $service, ApiResponse $response): JsonResponse
    {
        $service->updateProfile($request->user(), $organisation, $brand, $request->validated());

        return $response->success(['updated' => true]);
    }
}
