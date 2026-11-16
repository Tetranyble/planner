<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\CreateBrandRequest;
use App\Http\Resources\Directory\BrandSummaryResource;
use App\Models\Organisation;
use App\Services\Onboarding\CreateBrandService;
use Illuminate\Http\JsonResponse;

final class CreateBrandController extends Controller
{
    public function __invoke(
        CreateBrandRequest $request,
        Organisation $organisation,
        CreateBrandService $service,
        ApiResponse $response,
    ): JsonResponse {
        $attributes = $request->validated();
        $brand = $service->execute(
            $request->user(),
            $organisation,
            $attributes['name'],
            $attributes['website_url'] ?? null,
            $attributes['timezone'] ?? null,
        );

        return $response->created(BrandSummaryResource::make($brand)->resolve($request));
    }
}
