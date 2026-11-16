<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\UpdateClaimPolicyRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\ManageBrandStrategy;
use Illuminate\Http\JsonResponse;

final class BrandClaimPolicyController extends Controller
{
    public function update(UpdateClaimPolicyRequest $request, Organisation $organisation, Brand $brand, ManageBrandStrategy $service, ApiResponse $response): JsonResponse
    {
        $service->updateClaimPolicy($request->user(), $organisation, $brand, $request->validated());

        return $response->success(['updated' => true]);
    }
}
